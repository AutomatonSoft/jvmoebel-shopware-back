<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Parser;

final class AfterCoolSourceFileParser
{
    public function parse(?string $sourceFile): ?AfterCoolSourceFileMetadata
    {
        if (!is_string($sourceFile) || '' === trim($sourceFile)) {
            return null;
        }

        $fileName = trim($sourceFile);

        // Format A: prefix__region__factory_id.csv (e.g. GANASI_SOFA__CN__477277.csv)
        if (1 === preg_match('/^(?<prefix>.+)__(?<region>[^_]+)__(?<factory_id>\d+)\.csv$/', $fileName, $matches)) {
            return new AfterCoolSourceFileMetadata(
                $matches['prefix'],
                $matches['region'],
                (int) $matches['factory_id'],
            );
        }

        // Format C: prefix__factory_id.csv without region (e.g. Others_SOFORT_LIEFERBAR_DELETE_DONT_TOUCH__393532.csv)
        if (1 === preg_match('/^(?<prefix>.+)__(?<factory_id>\d+)\.csv$/', $fileName, $matches)) {
            return new AfterCoolSourceFileMetadata(
                $matches['prefix'],
                null,
                (int) $matches['factory_id'],
            );
        }

        // Format B: prefix_factory_id.csv (e.g. UK-GANASI_498371.csv)
        if (1 === preg_match('/^(?<prefix>.+)_(?<factory_id>\d+)\.csv$/', $fileName, $matches)) {
            return new AfterCoolSourceFileMetadata(
                $matches['prefix'],
                null,
                (int) $matches['factory_id'],
            );
        }

        return null;
    }
}
