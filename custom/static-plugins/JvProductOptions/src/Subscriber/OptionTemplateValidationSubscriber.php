<?php declare(strict_types=1);

namespace Jv\ProductOptions\Subscriber;

use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateGroup\OptionTemplateGroupDefinition;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValue\OptionTemplateValueCollection;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValue\OptionTemplateValueDefinition;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValue\OptionTemplateValueEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

final readonly class OptionTemplateValidationSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<OptionTemplateValueCollection> $valueRepository
     */
    public function __construct(
        private EntityRepository $valueRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'validate',
        ];
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        $existingValues = $this->loadUpdatedValues($event);

        foreach ($event->getCommands() as $command) {
            $entityName = $command->getEntityName();

            if (OptionTemplateValueDefinition::ENTITY_NAME === $entityName) {
                $this->validateValue($command, $existingValues, $event);
            } elseif (OptionTemplateGroupDefinition::ENTITY_NAME === $entityName) {
                $this->validateGroup($command, $event);
            }
        }
    }

    /**
     * @param array<string, OptionTemplateValueEntity> $existingValues
     */
    private function validateValue(WriteCommand $command, array $existingValues, PreWriteValidationEvent $event): void
    {
        $payload = $command->getPayload();
        $violations = new ConstraintViolationList();
        $id = $this->extractId($command->getPrimaryKey()['id'] ?? null);
        $existing = null === $id ? null : ($existingValues[$id] ?? null);

        $surchargeType = \array_key_exists('surcharge_type', $payload)
            ? $payload['surcharge_type']
            : $existing?->getSurchargeType();

        if (null !== $surchargeType && !\in_array($surchargeType, ['fixed', 'percentage'], true)) {
            $this->addViolation($violations, \sprintf('Invalid surcharge type "%s"', $surchargeType), 'surchargeType', $surchargeType);
        }

        $colorHex = $payload['color_hex'] ?? null;
        if (null !== $colorHex && (!\is_string($colorHex) || 1 !== preg_match('/^#[0-9A-Fa-f]{6}$/', $colorHex))) {
            $this->addViolation($violations, \sprintf('Invalid color hex "%s", must match #RRGGBB', (string) $colorHex), 'colorHex', $colorHex);
        }

        if ('fixed' === $surchargeType) {
            $price = \array_key_exists('surcharge_price', $payload)
                ? $payload['surcharge_price']
                : $this->serializePriceCollection($existing?->getSurchargePrice());
            $percentage = \array_key_exists('surcharge_percentage', $payload)
                ? $payload['surcharge_percentage']
                : $existing?->getSurchargePercentage();
            $this->validateFixedSurcharge($price, $percentage, $violations);
        } elseif ('percentage' === $surchargeType) {
            $percentage = \array_key_exists('surcharge_percentage', $payload)
                ? $payload['surcharge_percentage']
                : $existing?->getSurchargePercentage();
            $price = \array_key_exists('surcharge_price', $payload)
                ? $payload['surcharge_price']
                : $this->serializePriceCollection($existing?->getSurchargePrice());
            $this->validatePercentageSurcharge($percentage, $price, $violations);
        }

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }

    private function validateFixedSurcharge(mixed $rawPrice, mixed $percentage, ConstraintViolationList $violations): void
    {
        $this->validateFixedSurchargePrice($rawPrice, $violations);

        if (null !== $percentage) {
            $this->addViolation($violations, 'Percentage surcharge cannot be set when surcharge type is fixed', 'surchargePercentage', $percentage);
        }
    }

    private function validateFixedSurchargePrice(mixed $rawPrice, ConstraintViolationList $violations): void
    {
        $priceArray = \is_string($rawPrice) ? json_decode($rawPrice, true) : $rawPrice;

        if (!\is_array($priceArray) || [] === $priceArray) {
            $this->addViolation($violations, 'Fixed surcharge requires valid surchargePrice', 'surchargePrice', $rawPrice);

            return;
        }

        $defaultCurrencyPrice = null;
        $hasDefaultCurrency = false;
        foreach ($priceArray as $item) {
            if (!\is_array($item)) {
                $this->addViolation($violations, 'Fixed surchargePrice contains an invalid currency price', 'surchargePrice', $item);

                continue;
            }

            if (Defaults::CURRENCY === ($item['currencyId'] ?? null)) {
                $defaultCurrencyPrice = $item;
                $hasDefaultCurrency = true;
            }

            $net = $item['net'] ?? null;
            $gross = $item['gross'] ?? null;
            if (!\is_numeric($net) || !\is_numeric($gross) || (float) $net < 0 || (float) $gross < 0) {
                $this->addViolation($violations, 'Fixed surchargePrice amounts cannot be negative', 'surchargePrice', $item);
            }
        }

        if (!$hasDefaultCurrency || null === $defaultCurrencyPrice) {
            $this->addViolation($violations, 'Fixed surchargePrice must contain price for default currency', 'surchargePrice', $rawPrice);

            return;
        }
    }

    private function validatePercentageSurcharge(mixed $percentage, mixed $rawPrice, ConstraintViolationList $violations): void
    {
        if (!\is_numeric($percentage) || (float) $percentage < 0 || (float) $percentage > 1000) {
            $this->addViolation($violations, 'Percentage surcharge requires surchargePercentage', 'surchargePercentage', null);
        }

        if (null !== $rawPrice) {
            $this->addViolation($violations, 'Price surcharge cannot be set when surcharge type is percentage', 'surchargePrice', $rawPrice);
        }
    }

    /** @return array<string, OptionTemplateValueEntity> */
    private function loadUpdatedValues(PreWriteValidationEvent $event): array
    {
        $ids = [];
        foreach ($event->getCommands() as $command) {
            if (!$command instanceof UpdateCommand || OptionTemplateValueDefinition::ENTITY_NAME !== $command->getEntityName()) {
                continue;
            }

            $id = $this->extractId($command->getPrimaryKey()['id'] ?? null);
            if (null !== $id) {
                $ids[] = $id;
            }
        }

        if ([] === $ids) {
            return [];
        }

        /** @var iterable<OptionTemplateValueEntity> $values */
        $values = $this->valueRepository->search(new Criteria($ids), $event->getContext())->getEntities();
        $existing = [];
        foreach ($values as $value) {
            $existing[$value->getId()] = $value;
        }

        return $existing;
    }

    /** @return list<array{currencyId: string, gross: float, net: float}>|null */
    private function serializePriceCollection(?\Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection $prices): ?array
    {
        if (null === $prices) {
            return null;
        }

        $result = [];
        foreach ($prices as $price) {
            $result[] = [
                'currencyId' => $price->getCurrencyId(),
                'gross' => $price->getGross(),
                'net' => $price->getNet(),
            ];
        }

        return $result;
    }

    private function validateGroup(WriteCommand $command, PreWriteValidationEvent $event): void
    {
        $payload = $command->getPayload();

        if (!\array_key_exists('default_value_id', $payload)) {
            return;
        }

        $defaultValueId = $this->extractId($payload['default_value_id'] ?? null);
        if (null === $defaultValueId) {
            return;
        }

        $groupId = $this->extractId($command->getPrimaryKey()['id'] ?? null);
        if (null === $groupId) {
            return;
        }

        $valueGroupId = $this->findValueGroupId($defaultValueId, $event);

        if ($valueGroupId !== $groupId) {
            $violations = new ConstraintViolationList();
            $this->addViolation(
                $violations,
                \sprintf('defaultValueId "%s" must belong to group "%s"', $defaultValueId, $groupId),
                'defaultValueId',
                $defaultValueId
            );
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }

    private function findValueGroupId(string $defaultValueId, PreWriteValidationEvent $event): ?string
    {
        foreach ($event->getCommands() as $command) {
            if (OptionTemplateValueDefinition::ENTITY_NAME !== $command->getEntityName()) {
                continue;
            }

            if ($this->extractId($command->getPrimaryKey()['id'] ?? null) === $defaultValueId
                && \array_key_exists('group_id', $command->getPayload())) {
                return $this->extractId($command->getPayload()['group_id']);
            }
        }

        /** @var OptionTemplateValueEntity|null $value */
        $value = $this->valueRepository->search(new Criteria([$defaultValueId]), $event->getContext())->get($defaultValueId);

        return $value?->getGroupId();
    }

    private function addViolation(ConstraintViolationList $violations, string $message, string $propertyPath, mixed $invalidValue): void
    {
        $violations->add(new ConstraintViolation($message, null, [], null, $propertyPath, $invalidValue));
    }

    private function extractId(mixed $raw): ?string
    {
        if (!\is_string($raw)) {
            return null;
        }

        if (Uuid::isValid($raw)) {
            return strtolower($raw);
        }

        if (16 === \strlen($raw)) {
            return Uuid::fromBytesToHex($raw);
        }

        return null;
    }
}
