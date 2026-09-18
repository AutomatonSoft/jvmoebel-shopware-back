<?php declare(strict_types=1);

namespace Jv\ProductOptions\Service\OptionPricing;

final readonly class OptionLineItemIdGenerator
{
    /**
     * @param array<string, string> $selections
     */
    public function generate(string $productId, array $selections): string
    {
        ksort($selections);

        $parts = [];
        foreach ($selections as $groupId => $valueId) {
            $parts[] = $groupId.':'.$valueId;
        }

        return md5($productId.':'.implode('|', $parts));
    }
}
