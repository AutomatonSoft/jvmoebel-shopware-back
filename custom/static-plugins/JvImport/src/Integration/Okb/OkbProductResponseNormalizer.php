<?php declare(strict_types=1);

namespace Jv\Import\Integration\Okb;

use Jv\Import\Integration\Okb\Dto\OkbProductAttribute;
use Jv\Import\Integration\Okb\Dto\OkbProductVariation;

final class OkbProductResponseNormalizer
{
    /** @param array<string, mixed> $response */
    public function normalize(string $requestedEan, array $response): OkbProductVariation
    {
        $variations = $response['productVariations'] ?? null;
        if (!is_array($variations) || [] === $variations) {
            throw new \InvalidArgumentException(sprintf('OKB has no product for EAN "%s".', $requestedEan));
        }
        if (1 !== count($variations) || !is_array($variations[0])) {
            throw new \InvalidArgumentException(sprintf('OKB must return exactly one productVariation for EAN "%s".', $requestedEan));
        }
        /** @var array<string, mixed> $variation */
        $variation = $variations[0];
        $sku = $this->requiredString($variation, 'sku', $requestedEan);
        $ean = $this->requiredString($variation, 'ean', $requestedEan);
        if ($requestedEan !== $sku || $requestedEan !== $ean) {
            throw new \InvalidArgumentException(sprintf('OKB productVariation for EAN "%s" returned a different SKU or EAN.', $requestedEan));
        }

        return $this->variation($variation, $requestedEan);
    }

    /** @param array<string, mixed> $response
     * @return list<OkbProductVariation>
     */
    public function normalizeFamily(array $response): array
    {
        $variations = $response['productVariations'] ?? null;
        if (!is_array($variations)) {
            throw new \InvalidArgumentException('OKB family response has no productVariations.');
        }

        $family = [];
        foreach ($variations as $variation) {
            if (!is_array($variation)) {
                throw new \InvalidArgumentException('OKB family response has an invalid productVariation.');
            }
            $ean = $this->requiredString($variation, 'ean', 'family');
            $family[] = $this->variation($variation, $ean);
        }

        return $family;
    }

    /** @param array<string, mixed> $variation */
    private function variation(array $variation, string $requestedEan): OkbProductVariation
    {
        $sku = $this->requiredString($variation, 'sku', $requestedEan);
        $ean = $this->requiredString($variation, 'ean', $requestedEan);
        $productReference = $this->requiredString($variation, 'productReference', $requestedEan);
        $description = $variation['productDescription'] ?? null;
        if (!is_array($description)) {
            throw new \InvalidArgumentException(sprintf('OKB productVariation for EAN "%s" has no productDescription.', $requestedEan));
        }
        $rawPricing = $variation['pricing'] ?? [];
        $pricing = is_array($rawPricing) ? $rawPricing : [];
        $standardPrice = $pricing['standardPrice'] ?? null;
        if (null !== $standardPrice && !is_array($standardPrice)) {
            throw new \InvalidArgumentException(sprintf('OKB productVariation for EAN "%s" has invalid standardPrice.', $requestedEan));
        }
        $msrp = $pricing['msrp'] ?? null;
        if (null !== $msrp && !is_array($msrp)) {
            throw new \InvalidArgumentException(sprintf('OKB productVariation for EAN "%s" has invalid msrp.', $requestedEan));
        }

        return new OkbProductVariation(
            $sku,
            $ean,
            $productReference,
            $this->requiredString($description, 'category', $requestedEan),
            $this->nullableAmount($standardPrice, $requestedEan),
            $this->nullableCurrency($standardPrice, $requestedEan),
            $this->attributes($description, $requestedEan),
            $this->nullableAmount($msrp, $requestedEan),
        );
    }

    /** @param array<string, mixed> $record */
    private function requiredString(array $record, string $key, string $requestedEan): string
    {
        $value = $record[$key] ?? null;
        if (!is_string($value) || '' === trim($value)) {
            throw new \InvalidArgumentException(sprintf('OKB productVariation for EAN "%s" is missing %s.', $requestedEan, $key));
        }

        return trim($value);
    }

    /** @param array<string, mixed>|null $price */
    private function nullableAmount(?array $price, string $requestedEan): ?float
    {
        if (null === $price || !array_key_exists('amount', $price)) {
            return null;
        }
        $amount = $price['amount'];
        if (!is_int($amount) && !is_float($amount)) {
            throw new \InvalidArgumentException(sprintf('OKB price for EAN "%s" has an invalid amount.', $requestedEan));
        }

        return (float) $amount;
    }

    /** @param array<string, mixed>|null $standardPrice */
    private function nullableCurrency(?array $standardPrice, string $requestedEan): ?string
    {
        if (null === $standardPrice || !array_key_exists('currency', $standardPrice)) {
            return null;
        }
        $currency = $standardPrice['currency'];
        if (!is_string($currency) || '' === trim($currency)) {
            throw new \InvalidArgumentException(sprintf('OKB standardPrice for EAN "%s" has an invalid currency.', $requestedEan));
        }

        return trim($currency);
    }

    /**
     * @param array<string, mixed> $description
     *
     * @return list<OkbProductAttribute>
     */
    private function attributes(array $description, string $requestedEan): array
    {
        $rawAttributes = $description['attributes'] ?? [];
        if (!is_array($rawAttributes)) {
            throw new \InvalidArgumentException(sprintf('OKB productVariation for EAN "%s" has invalid attributes.', $requestedEan));
        }
        $attributes = [];
        foreach ($rawAttributes as $rawAttribute) {
            if (!is_array($rawAttribute)) {
                throw new \InvalidArgumentException(sprintf('OKB productVariation for EAN "%s" has an invalid attribute entry.', $requestedEan));
            }
            $name = $this->requiredString($rawAttribute, 'name', $requestedEan);
            $values = $rawAttribute['values'] ?? null;
            if (!is_array($values) || !array_is_list($values) || array_any($values, static fn (mixed $value): bool => !is_string($value) || '' === trim($value))) {
                throw new \InvalidArgumentException(sprintf('OKB attribute "%s" for EAN "%s" has invalid values.', $name, $requestedEan));
            }
            $attributes[] = new OkbProductAttribute($name, array_map(trim(...), $values));
        }

        return $attributes;
    }
}
