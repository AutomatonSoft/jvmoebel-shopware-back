<?php declare(strict_types=1);

namespace Jv\Cms\Service\Validation;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlotTranslation\CmsSlotTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

final readonly class CmsSlotWriteValidator implements EventSubscriberInterface
{
    public function __construct(
        private CmsElementValidator $validator,
        private Connection $connection,
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
        $slotTypes = $this->slotTypesFromCommands($event->getCommands());

        foreach ($event->getCommands() as $command) {
            if (!$this->isSlotTranslationWrite($command)) {
                continue;
            }

            $config = $this->configFromCommand($command);
            if (null === $config) {
                continue;
            }

            $elementType = $this->elementType($command, $slotTypes);
            if (null === $elementType) {
                continue;
            }

            $violations = new ConstraintViolationList();
            foreach ($this->validator->validate($elementType, $config) as $error) {
                if (!$error->blockSave) {
                    continue;
                }

                $violations->add(new ConstraintViolation(
                    $error->message,
                    $error->message,
                    [],
                    $config,
                    $error->fieldPath,
                    null,
                    null,
                    $error->code,
                ));
            }

            if ($violations->count() > 0) {
                $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
            }
        }
    }

    /**
     * @param list<WriteCommand> $commands
     *
     * @return array<string, string>
     */
    private function slotTypesFromCommands(array $commands): array
    {
        $slotTypes = [];

        foreach ($commands as $command) {
            if (CmsSlotDefinition::ENTITY_NAME !== $command->getEntityName()) {
                continue;
            }

            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            $type = $command->getPayload()['type'] ?? null;
            $primaryKey = $command->getPrimaryKey();
            if (!\is_string($type) || !isset($primaryKey['id'], $primaryKey['version_id'])) {
                continue;
            }

            $slotTypes[$this->slotKey($primaryKey['id'], $primaryKey['version_id'])] = $type;
        }

        return $slotTypes;
    }

    private function isSlotTranslationWrite(WriteCommand $command): bool
    {
        return CmsSlotTranslationDefinition::ENTITY_NAME === $command->getEntityName()
            && ($command instanceof InsertCommand || $command instanceof UpdateCommand)
            && \array_key_exists('config', $command->getPayload());
    }

    /** @return array<string, mixed>|null */
    private function configFromCommand(WriteCommand $command): ?array
    {
        $config = $command->getPayload()['config'];
        if (\is_array($config)) {
            return $config;
        }

        if (!\is_string($config)) {
            return null;
        }

        $decoded = json_decode($config, true);

        return \is_array($decoded) ? $decoded : null;
    }

    /** @param array<string, string> $slotTypes */
    private function elementType(WriteCommand $command, array $slotTypes): ?string
    {
        $primaryKey = $command->getPrimaryKey();
        if (!isset($primaryKey['cms_slot_id'], $primaryKey['cms_slot_version_id'])) {
            return null;
        }

        $slotKey = $this->slotKey($primaryKey['cms_slot_id'], $primaryKey['cms_slot_version_id']);
        if (isset($slotTypes[$slotKey])) {
            return $slotTypes[$slotKey];
        }

        $type = $this->connection->fetchOne(
            'SELECT `type` FROM `cms_slot` WHERE `id` = :id AND `version_id` = :versionId',
            [
                'id' => $primaryKey['cms_slot_id'],
                'versionId' => $primaryKey['cms_slot_version_id'],
            ],
        );

        return \is_string($type) ? $type : null;
    }

    private function slotKey(string $id, string $versionId): string
    {
        return base64_encode($id.$versionId);
    }
}
