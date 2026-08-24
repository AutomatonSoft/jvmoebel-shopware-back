<?php

declare(strict_types=1);

namespace Jv\Cms\Service\Search;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

final class QueryFilterInterpreterFactory
{
    /**
     * @param EntityRepository<PropertyGroupOptionCollection> $propertyGroupOptionRepository
     */
    public function __construct(
        private readonly SynonymDictionaryLoader $loader,
        private readonly string $projectDir,
        private readonly EntityRepository $propertyGroupOptionRepository,
    ) {
    }

    public function create(): QueryFilterInterpreter
    {
        $candidates = [
            $this->projectDir.'/custom/static-plugins/JvCms/src/Resources/config/search-synonyms.json',
            \dirname(__DIR__, 2).'/Resources/config/search-synonyms.json',
        ];

        $dictionary = [];
        foreach ($candidates as $path) {
            $dictionary = $this->loader->load($path);
            if ([] !== $dictionary) {
                break;
            }
            // Prefer an existing file even if entries are empty (valid empty dictionary).
            if (is_file($path)) {
                break;
            }
        }

        // Whitelist option IDs against DAL so stale synonym UUIDs cannot consume query tokens.
        return new QueryFilterInterpreter($dictionary, $this->resolveOptionGroupIds($dictionary));
    }

    /**
     * @param iterable<mixed> $dictionary
     *
     * @return array<string, string> optionId → groupId
     */
    private function resolveOptionGroupIds(iterable $dictionary): array
    {
        $candidateIds = [];
        foreach ($dictionary as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $optionId = trim((string) ($row['optionId'] ?? ''));
            if (Uuid::isValid($optionId)) {
                $candidateIds[$optionId] = true;
            }
        }

        if ([] === $candidateIds) {
            return [];
        }

        $ids = array_keys($candidateIds);
        $criteria = new Criteria($ids);
        $criteria->setLimit(\count($ids));

        $map = [];
        /** @var \Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity $option */
        foreach ($this->propertyGroupOptionRepository->search($criteria, Context::createDefaultContext()) as $option) {
            $map[$option->getId()] = $option->getGroupId();
        }

        return $map;
    }
}
