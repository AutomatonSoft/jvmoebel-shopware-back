<?php declare(strict_types=1);

namespace Jv\Import\Integration\AfterCool\Mapper;

use Jv\Import\Integration\AfterCool\Dto\AfterCoolMappedProduct;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolProductIssue;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolProductItem;
use Jv\Import\Integration\AfterCool\Exception\AfterCoolProductMappingException;

final class AfterCoolListerProductMapper
{
    public function map(AfterCoolProductItem $item, ?AfterCoolProductItem $linkedProduct = null, bool $importing = false): AfterCoolMappedProduct
    {
        if (!$this->isValidEan($item->ean)) {
            throw new AfterCoolProductMappingException($item->productId, 'invalid_ean');
        }
        $price = $this->price($item);
        $stock = $this->stock($item);
        [$mediaUrls, $mediaIssues] = $this->media($item);

        return new AfterCoolMappedProduct(
            $item->account,
            $item->dataset,
            $item->factoryId,
            $item->productId,
            $item->artikelnummer,
            $item->ean,
            $item->ean,
            $item->name,
            $item->rowNo,
            $price,
            $stock,
            $this->description($item, $linkedProduct, $importing),
            $mediaUrls,
            $mediaIssues,
            null,
            null,
            null,
            $item->updatedAt,
            $item->sourceFile,
            $item->sourceKind,
        );
    }

    public function mapWithUnusablePrice(AfterCoolProductItem $item): AfterCoolMappedProduct
    {
        if (!$this->isValidEan($item->ean)) {
            throw new AfterCoolProductMappingException($item->productId, 'invalid_ean');
        }
        [$mediaUrls, $mediaIssues] = $this->media($item);

        return new AfterCoolMappedProduct(
            $item->account,
            $item->dataset,
            $item->factoryId,
            $item->productId,
            $item->artikelnummer,
            $item->ean,
            $item->ean,
            $item->name,
            $item->rowNo,
            null,
            $this->stock($item),
            $this->description($item, null, false),
            $mediaUrls,
            $mediaIssues,
            null,
            null,
            null,
            $item->updatedAt,
            $item->sourceFile,
            $item->sourceKind,
        );
    }

    private function isValidEan(string $ean): bool
    {
        if (!preg_match('/^\d{13}$/', $ean)) {
            return false;
        }
        $sum = 0;
        for ($index = 0; $index < 12; ++$index) {
            $sum += (int) $ean[$index] * (0 === $index % 2 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10 === (int) $ean[12];
    }

    private function price(AfterCoolProductItem $item): float
    {
        $value = $item->row['Startpreis'] ?? null;
        if ((!is_string($value) && !is_int($value) && !is_float($value)) || !is_numeric($value)) {
            throw new AfterCoolProductMappingException($item->productId, 'invalid_price');
        }

        return (float) $value;
    }

    private function stock(AfterCoolProductItem $item): int
    {
        $value = $item->row['Menge'] ?? null;
        if ((is_string($value) && !preg_match('/^\d+$/', $value)) || (is_int($value) && 0 > $value) || (!is_string($value) && !is_int($value))) {
            throw new AfterCoolProductMappingException($item->productId, 'invalid_stock');
        }

        return (int) $value;
    }

    private function description(AfterCoolProductItem $item, ?AfterCoolProductItem $linkedProduct, bool $importing): ?string
    {
        if (null !== $linkedProduct) {
            $description = $linkedProduct->row['Beschreibung'] ?? null;

            return is_string($description) && '' !== trim($description) ? $description : null;
        }
        if ($importing) {
            return null;
        }
        $description = $item->row['Description'] ?? null;
        if (!is_string($description) || '' === trim($description) || str_contains($description, '<-StammBeschreibung->')) {
            return null;
        }

        return trim($description);
    }

    /** @return array{list<string>, list<AfterCoolProductIssue>} */
    private function media(AfterCoolProductItem $item): array
    {
        $candidates = [];
        foreach (['GalleryURL', 'pictureurls'] as $field) {
            $value = $item->row[$field] ?? null;
            foreach (is_array($value) ? $value : (is_string($value) ? preg_split('/[|;]/', $value) : []) as $url) {
                if (!is_string($url) || '' === trim($url)) {
                    continue;
                }
                $candidates[] = trim($url);
            }
        }
        $urls = [];
        $issues = [];
        foreach ($candidates as $url) {
            $scheme = parse_url($url, PHP_URL_SCHEME);
            if (!is_string($scheme) || !in_array(strtolower($scheme), ['http', 'https'], true) || false === filter_var($url, FILTER_VALIDATE_URL)) {
                $issues[] = new AfterCoolProductIssue($item->productId, 'failed', 'invalid_media_url', 'External media URL is invalid.', $item->artikelnummer, $item->ean, $item->rowNo);

                continue;
            }
            if (!in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        return [$urls, $issues];
    }
}
