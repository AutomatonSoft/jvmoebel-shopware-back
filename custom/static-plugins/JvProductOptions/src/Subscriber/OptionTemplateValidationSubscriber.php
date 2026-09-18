<?php declare(strict_types=1);

namespace Jv\ProductOptions\Subscriber;

use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateGroup\OptionTemplateGroupDefinition;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValue\OptionTemplateValueCollection;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValue\OptionTemplateValueDefinition;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValue\OptionTemplateValueEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
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
        foreach ($event->getCommands() as $command) {
            $entityName = $command->getEntityName();

            if (OptionTemplateValueDefinition::ENTITY_NAME === $entityName) {
                $this->validateValue($command, $event);
            } elseif (OptionTemplateGroupDefinition::ENTITY_NAME === $entityName) {
                $this->validateGroup($command, $event);
            }
        }
    }

    private function validateValue(WriteCommand $command, PreWriteValidationEvent $event): void
    {
        $payload = $command->getPayload();
        $violations = new ConstraintViolationList();

        $surchargeType = $payload['surcharge_type'] ?? $this->existingSurchargeType($command, $event);

        if (null !== $surchargeType && !\in_array($surchargeType, ['fixed', 'percentage'], true)) {
            $this->addViolation($violations, \sprintf('Invalid surcharge type "%s"', $surchargeType), 'surchargeType', $surchargeType);
        }

        $colorHex = $payload['color_hex'] ?? null;
        if (null !== $colorHex && (!\is_string($colorHex) || 1 !== preg_match('/^#[0-9A-Fa-f]{6}$/', $colorHex))) {
            $this->addViolation($violations, \sprintf('Invalid color hex "%s", must match #RRGGBB', (string) $colorHex), 'colorHex', $colorHex);
        }

        if ('fixed' === $surchargeType) {
            $this->validateFixedSurcharge($command, $payload, $violations);
        } elseif ('percentage' === $surchargeType) {
            $this->validatePercentageSurcharge($command, $payload, $violations);
        }

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateFixedSurcharge(WriteCommand $command, array $payload, ConstraintViolationList $violations): void
    {
        $hasPriceKey = \array_key_exists('surcharge_price', $payload);
        $rawPrice = $payload['surcharge_price'] ?? null;

        if ($command instanceof InsertCommand && !$hasPriceKey) {
            $this->addViolation($violations, 'Fixed surcharge requires surchargePrice', 'surchargePrice', null);
        } elseif ($hasPriceKey) {
            $this->validateFixedSurchargePrice($rawPrice, $violations);
        }

        $percentage = $payload['surcharge_percentage'] ?? null;
        if (\array_key_exists('surcharge_percentage', $payload) && null !== $percentage) {
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
        foreach ($priceArray as $item) {
            if (\is_array($item) && Defaults::CURRENCY === ($item['currencyId'] ?? null)) {
                $defaultCurrencyPrice = $item;

                break;
            }
        }

        if (null === $defaultCurrencyPrice) {
            $this->addViolation($violations, 'Fixed surchargePrice must contain price for default currency', 'surchargePrice', $rawPrice);

            return;
        }

        $net = $defaultCurrencyPrice['net'] ?? null;
        $gross = $defaultCurrencyPrice['gross'] ?? null;
        if (!\is_numeric($net) || !\is_numeric($gross) || (float) $net < 0 || (float) $gross < 0) {
            $this->addViolation($violations, 'Fixed surchargePrice amounts cannot be negative', 'surchargePrice', $rawPrice);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validatePercentageSurcharge(WriteCommand $command, array $payload, ConstraintViolationList $violations): void
    {
        $hasPercentageKey = \array_key_exists('surcharge_percentage', $payload);
        $percentage = $payload['surcharge_percentage'] ?? null;

        if ($command instanceof InsertCommand && !$hasPercentageKey) {
            $this->addViolation($violations, 'Percentage surcharge requires surchargePercentage', 'surchargePercentage', null);
        } elseif ($hasPercentageKey && (!\is_numeric($percentage) || (float) $percentage < 0 || (float) $percentage > 1000)) {
            $this->addViolation($violations, 'Percentage surcharge must be between 0 and 1000', 'surchargePercentage', $percentage);
        }

        $rawPrice = $payload['surcharge_price'] ?? null;
        if (\array_key_exists('surcharge_price', $payload) && null !== $rawPrice) {
            $this->addViolation($violations, 'Price surcharge cannot be set when surcharge type is percentage', 'surchargePrice', $rawPrice);
        }
    }

    private function existingSurchargeType(WriteCommand $command, PreWriteValidationEvent $event): ?string
    {
        if (!$command instanceof UpdateCommand) {
            return null;
        }

        $pk = $this->extractId($command->getPrimaryKey()['id'] ?? null);
        if (null === $pk) {
            return null;
        }

        /** @var OptionTemplateValueEntity|null $existing */
        $existing = $this->valueRepository->search(new Criteria([$pk]), $event->getContext())->get($pk);

        return $existing?->getSurchargeType();
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

            if ($this->extractId($command->getPrimaryKey()['id'] ?? null) === $defaultValueId) {
                return $this->extractId($command->getPayload()['group_id'] ?? null);
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
