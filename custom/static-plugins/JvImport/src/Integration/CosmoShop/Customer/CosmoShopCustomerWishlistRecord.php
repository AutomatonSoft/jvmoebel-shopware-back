<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Customer;

final readonly class CosmoShopCustomerWishlistRecord
{
    public function __construct(
        public ?int $sourceCustomerId,
        public ?int $sourceListId,
        public ?int $sourceArticleId,
        public string $productNumber,
        public bool $isWellFormed,
    ) {
    }
}
