<?php declare(strict_types=1);

namespace Jv\Seo\Contract;

final readonly class ImportImageRedirectData
{
    public function __construct(
        public string $sourceSystem,
        public string $sourceMarket,
        public string $sourceIdentifier,
        public string $mediaId,
        public string $salesChannelId,
        public string $sourceUrl,
    ) {
    }
}
