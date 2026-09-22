<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Media;

use Jv\Import\Integration\CosmoShop\Media\Dto\CosmoShopLegacyProductImageUrls;

final class CosmoShopLegacyProductImageUrlExpander
{
    public function expand(string $sourceUrl): ?CosmoShopLegacyProductImageUrls
    {
        $sourceUrl = trim($sourceUrl);
        if (!preg_match('~^https?://[^/]+~i', $sourceUrl)) {
            return null;
        }

        $resourceLength = strcspn($sourceUrl, '?#');
        $resourceUrl = substr($sourceUrl, 0, $resourceLength);
        $suffix = substr($sourceUrl, $resourceLength);
        $marker = '/pix/a/';
        $markerPosition = strrpos($resourceUrl, $marker);
        if (false === $markerPosition) {
            return null;
        }

        $prefix = substr($resourceUrl, 0, $markerPosition + strlen($marker));
        $path = substr($resourceUrl, $markerPosition + strlen($marker));

        if (preg_match('~^(?<type>v|n|g)/(?<file>[^/]+)$~D', $path, $matches)) {
            return $this->mainImageUrls($prefix, $matches['file'], $suffix);
        }

        if (preg_match('~^(?:z/(?<sku>[^/]+)(?:/g)?|zg/(?<legacySku>[^/]+))/(?<file>[^/]+)$~D', $path, $matches)) {
            $sku = '' !== $matches['sku'] ? $matches['sku'] : $matches['legacySku'];

            return new CosmoShopLegacyProductImageUrls(
                'gallery:'.$sku.':'.$matches['file'],
                [
                    $prefix.'z/'.$sku.'/'.$matches['file'].$suffix,
                    $prefix.'z/'.$sku.'/g/'.$matches['file'].$suffix,
                    $prefix.'zg/'.$sku.'/'.$matches['file'].$suffix,
                ],
            );
        }

        return null;
    }

    private function mainImageUrls(string $prefix, string $file, string $suffix): CosmoShopLegacyProductImageUrls
    {
        if (preg_match('~^(?<base>.+)-[012](?<version>\.\d+)?(?<extension>\.[^./]+)$~D', $file, $matches)) {
            $version = $matches['version'];
            $files = [
                'v' => $matches['base'].'-0'.$version.$matches['extension'],
                'n' => $matches['base'].'-1'.$version.$matches['extension'],
                'g' => $matches['base'].'-2'.$version.$matches['extension'],
            ];
            $targetKey = 'main:'.$matches['base'].$version.$matches['extension'];
        } else {
            $files = ['v' => $file, 'n' => $file, 'g' => $file];
            $targetKey = 'main:'.$file;
        }

        return new CosmoShopLegacyProductImageUrls(
            $targetKey,
            array_values(array_unique([
                $prefix.'v/'.$files['v'].$suffix,
                $prefix.'n/'.$files['n'].$suffix,
                $prefix.'g/'.$files['g'].$suffix,
            ])),
        );
    }
}
