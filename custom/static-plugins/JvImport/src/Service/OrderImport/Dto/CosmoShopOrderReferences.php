<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport\Dto;

use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final readonly class CosmoShopOrderReferences
{
    /** @param array<string, string> $countryIds
     * @param array<string, string> $states
     * @param array<string, string> $salutations
     */
    public function __construct(public SalesChannelEntity $salesChannel, public array $countryIds, public array $states, public array $salutations)
    {
    }
}
