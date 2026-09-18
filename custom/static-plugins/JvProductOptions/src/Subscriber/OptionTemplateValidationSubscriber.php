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
        $commands = $event->getCommands();

        foreach ($commands as $command) {
            $entityName = $command->getEntityName();

            if ($entityName === OptionTemplateValueDefinition::ENTITY_NAME) {
                $this->validateValue($command, $event);
            } elseif ($entityName === OptionTemplateGroupDefinition::ENTITY_NAME) {
                $this->validateGroup($command, $event);
            }
        }
    }

    private function validateValue(WriteCommand $command, PreWriteValidationEvent $event): void
    {
        $payload = $command->getPayload();
        $violations = new ConstraintViolationList();

        $surchargeType = $payload['surcharge_type'] ?? $payload['surchargeType'] ?? null;

        if ($surchargeType === null && $command instanceof UpdateCommand) {
            $pk = $this->extractId($command->getPrimaryKey()['id'] ?? null);
            if ($pk !== null) {
                /** @var OptionTemplateValueEntity|null $existing */
                $existing = $this->valueRepository->search(new Criteria([$pk]), $event->getContext())->get($pk);
                $surchargeType = $existing?->getSurchargeType();
            }
        }

        if ($surchargeType !== null && !in_array($surchargeType, ['fixed', 'percentage'], true)) {
            $violations->add(new ConstraintViolation(
                sprintf('Invalid surcharge type "%s"', (string) $surchargeType),
                null,
                [],
                null,
                'surchargeType',
                $surchargeType
            ));
        }

        $colorHex = $payload['color_hex'] ?? $payload['colorHex'] ?? null;
        if ($colorHex !== null && (!is_string($colorHex) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $colorHex))) {
            $violations->add(new ConstraintViolation(
                sprintf('Invalid color hex "%s", must match #RRGGBB', (string) $colorHex),
                null,
                [],
                null,
                'colorHex',
                $colorHex
            ));
        }

        if ($surchargeType === 'fixed') {
            $hasPriceKey = array_key_exists('surcharge_price', $payload) || array_key_exists('surchargePrice', $payload);
            $rawPrice = $payload['surcharge_price'] ?? $payload['surchargePrice'] ?? null;
            $priceArray = null;

            if ($rawPrice !== null) {
                if (is_string($rawPrice)) {
                    $decoded = json_decode($rawPrice, true);
                    if (is_array($decoded)) {
                        $priceArray = $decoded;
                    }
                } elseif (is_array($rawPrice)) {
                    $priceArray = $rawPrice;
                }
            }

            if ($command instanceof InsertCommand && !$hasPriceKey) {
                $violations->add(new ConstraintViolation(
                    'Fixed surcharge requires surchargePrice',
                    null,
                    [],
                    null,
                    'surchargePrice',
                    null
                ));
            } elseif ($hasPriceKey) {
                if ($priceArray === null || $priceArray === []) {
                    $violations->add(new ConstraintViolation(
                        'Fixed surcharge requires valid surchargePrice',
                        null,
                        [],
                        null,
                        'surchargePrice',
                        $rawPrice
                    ));
                } else {
                    $defaultCurrencyPrice = null;
                    foreach ($priceArray as $item) {
                        if (is_array($item) && ($item['currencyId'] ?? null) === Defaults::CURRENCY) {
                            $defaultCurrencyPrice = $item;
                            break;
                        }
                    }

                    if ($defaultCurrencyPrice === null) {
                        $violations->add(new ConstraintViolation(
                            'Fixed surchargePrice must contain price for default currency',
                            null,
                            [],
                            null,
                            'surchargePrice',
                            $rawPrice
                        ));
                    } else {
                        $net = $defaultCurrencyPrice['net'] ?? null;
                        $gross = $defaultCurrencyPrice['gross'] ?? null;
                        if (!is_numeric($net) || !is_numeric($gross) || (float) $net < 0 || (float) $gross < 0) {
                            $violations->add(new ConstraintViolation(
                                'Fixed surchargePrice amounts cannot be negative',
                                null,
                                [],
                                null,
                                'surchargePrice',
                                $rawPrice
                            ));
                        }
                    }
                }
            }

            $hasPercentageKey = array_key_exists('surcharge_percentage', $payload) || array_key_exists('surchargePercentage', $payload);
            $percentage = $payload['surcharge_percentage'] ?? $payload['surchargePercentage'] ?? null;
            if ($command instanceof InsertCommand) {
                if ($hasPercentageKey && $percentage !== null) {
                    $violations->add(new ConstraintViolation(
                        'Percentage surcharge cannot be set when surcharge type is fixed',
                        null,
                        [],
                        null,
                        'surchargePercentage',
                        $percentage
                    ));
                }
            } elseif ($hasPercentageKey && $percentage !== null) {
                $violations->add(new ConstraintViolation(
                    'Percentage surcharge cannot be set when surcharge type is fixed',
                    null,
                    [],
                    null,
                    'surchargePercentage',
                    $percentage
                ));
            }
        } elseif ($surchargeType === 'percentage') {
            $hasPercentageKey = array_key_exists('surcharge_percentage', $payload) || array_key_exists('surchargePercentage', $payload);
            $percentage = $payload['surcharge_percentage'] ?? $payload['surchargePercentage'] ?? null;

            if ($command instanceof InsertCommand && !$hasPercentageKey) {
                $violations->add(new ConstraintViolation(
                    'Percentage surcharge requires surchargePercentage',
                    null,
                    [],
                    null,
                    'surchargePercentage',
                    null
                ));
            } elseif ($hasPercentageKey) {
                if (!is_numeric($percentage) || (float) $percentage < 0 || (float) $percentage > 1000) {
                    $violations->add(new ConstraintViolation(
                        'Percentage surcharge must be between 0 and 1000',
                        null,
                        [],
                        null,
                        'surchargePercentage',
                        $percentage
                    ));
                }
            }

            $hasPriceKey = array_key_exists('surcharge_price', $payload) || array_key_exists('surchargePrice', $payload);
            $rawPrice = $payload['surcharge_price'] ?? $payload['surchargePrice'] ?? null;
            if ($hasPriceKey && $rawPrice !== null) {
                $violations->add(new ConstraintViolation(
                    'Price surcharge cannot be set when surcharge type is percentage',
                    null,
                    [],
                    null,
                    'surchargePrice',
                    $rawPrice
                ));
            }
        }

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }

    private function validateGroup(WriteCommand $command, PreWriteValidationEvent $event): void
    {
        $payload = $command->getPayload();

        $hasDefaultValueIdKey = array_key_exists('default_value_id', $payload) || array_key_exists('defaultValueId', $payload);
        if (!$hasDefaultValueIdKey) {
            return;
        }

        $rawDefaultValId = $payload['default_value_id'] ?? $payload['defaultValueId'] ?? null;
        $defaultValueId = $this->extractId($rawDefaultValId);

        if ($defaultValueId === null) {
            return;
        }

        $groupId = $this->extractId($command->getPrimaryKey()['id'] ?? null);
        if ($groupId === null) {
            return;
        }

        $valueGroupId = null;

        foreach ($event->getCommands() as $otherCommand) {
            if ($otherCommand->getEntityName() !== OptionTemplateValueDefinition::ENTITY_NAME) {
                continue;
            }

            $otherId = $this->extractId($otherCommand->getPrimaryKey()['id'] ?? null);
            if ($otherId === $defaultValueId) {
                $otherPayload = $otherCommand->getPayload();
                $rawValGroup = $otherPayload['group_id'] ?? $otherPayload['groupId'] ?? null;
                $valueGroupId = $this->extractId($rawValGroup);
                break;
            }
        }

        if ($valueGroupId === null) {
            /** @var OptionTemplateValueEntity|null $val */
            $val = $this->valueRepository->search(new Criteria([$defaultValueId]), $event->getContext())->get($defaultValueId);
            $valueGroupId = $val?->getGroupId();
        }

        if ($valueGroupId !== $groupId) {
            $violations = new ConstraintViolationList();
            $violations->add(new ConstraintViolation(
                sprintf('defaultValueId "%s" must belong to group "%s"', $defaultValueId, $groupId),
                null,
                [],
                null,
                'defaultValueId',
                $defaultValueId
            ));
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }

    private function extractId(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        if (is_string($raw)) {
            if (Uuid::isValid($raw)) {
                return strtolower($raw);
            }

            if (strlen($raw) === 16) {
                return Uuid::fromBytesToHex($raw);
            }
        }

        return null;
    }
}
