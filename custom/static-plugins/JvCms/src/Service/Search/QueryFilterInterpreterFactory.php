<?php

declare(strict_types=1);

namespace Jv\Cms\Service\Search;

final class QueryFilterInterpreterFactory
{
    public function __construct(
        private readonly SynonymDictionaryLoader $loader,
        private readonly string $projectDir,
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

        return new QueryFilterInterpreter($dictionary);
    }
}
