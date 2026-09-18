<?php declare(strict_types=1);

namespace Jv\ProductOptions\Service\OptionPricing;

use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValue\OptionTemplateValueEntity;
use Jv\ProductOptions\Core\Content\OptionTemplate\OptionTemplateEntity;
use Jv\ProductOptions\Service\OptionPricing\Exception\InvalidOptionSelectionException;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class OptionSelectionResolver
{
    /**
     * @param mixed $selections
     * @return list<OptionTemplateValueEntity>
     */
    public function resolve(OptionTemplateEntity $template, mixed $selections): array
    {
        if ($selections !== null && !is_array($selections)) {
            throw new InvalidOptionSelectionException('Selections must be an associative array');
        }

        if (is_array($selections) && array_is_list($selections) && $selections !== []) {
            throw new InvalidOptionSelectionException('Selections must be an associative array, list given');
        }

        $normalizedSelections = $selections ?? [];

        $groups = $template->getGroups();
        if ($groups === null || $groups->count() === 0) {
            if ($normalizedSelections !== []) {
                throw new InvalidOptionSelectionException('Selections provided for template without groups');
            }

            return [];
        }

        $groupList = $groups->getElements();
        usort($groupList, static fn ($a, $b) => $a->getPosition() <=> $b->getPosition());

        $groupMap = [];
        foreach ($groupList as $group) {
            $groupMap[$group->getId()] = $group;
        }

        foreach ($normalizedSelections as $groupId => $valueId) {
            if (!is_string($groupId) || !Uuid::isValid($groupId)) {
                throw new InvalidOptionSelectionException(sprintf('Group id "%s" is not a valid UUID', (string) $groupId));
            }

            if (!isset($groupMap[$groupId])) {
                throw new InvalidOptionSelectionException(sprintf('Group "%s" does not belong to the template', $groupId));
            }
        }

        $resolved = [];

        foreach ($groupList as $group) {
            $groupId = $group->getId();
            $values = $group->getValues();
            $valueMap = [];

            if ($values !== null) {
                foreach ($values as $value) {
                    $valueMap[$value->getId()] = $value;
                }
            }

            $selectedId = null;

            if (array_key_exists($groupId, $normalizedSelections)) {
                $rawValId = $normalizedSelections[$groupId];

                if (!is_string($rawValId) || !Uuid::isValid($rawValId)) {
                    throw new InvalidOptionSelectionException(sprintf('Value id "%s" is not a valid UUID', (string) $rawValId));
                }

                $selectedId = $rawValId;
            } else {
                $selectedId = $group->getDefaultValueId();
            }

            if ($selectedId === null) {
                throw new InvalidOptionSelectionException(sprintf('No option selected and no default value defined for group "%s"', $groupId));
            }

            if (!isset($valueMap[$selectedId])) {
                throw new InvalidOptionSelectionException(sprintf('Value "%s" does not belong to group "%s"', $selectedId, $groupId));
            }

            $resolved[] = $valueMap[$selectedId];
        }

        return $resolved;
    }
}
