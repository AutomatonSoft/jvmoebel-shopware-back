<?php declare(strict_types=1);

namespace Jv\Seo\Contract;

final readonly class ImportProductRedirectData
{
    public function __construct(
        public string $sourceSystem,
        public string $sourceMarket,
        public string $sourceIdentifier,
        public string $productId,
        public string $salesChannelId,
        public string $sourceUrl,
    ) {
    }
}
