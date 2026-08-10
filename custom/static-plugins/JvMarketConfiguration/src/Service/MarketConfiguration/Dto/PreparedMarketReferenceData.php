<?php declare(strict_types=1);

namespace Jv\MarketConfiguration\Service\MarketConfiguration\Dto;

use Jv\MarketConfiguration\Service\MarketConfiguration\Market;

final readonly class PreparedMarketReferenceData
{
    /**
     * @param array<string, string> $languageIds
     * @param array<string, string> $marketLanguageIds
     * @param array<string, string> $currencyIds
     * @param array<string, string> $snippetSetIds
     */
    public function __construct(
        private array $languageIds,
        private array $marketLanguageIds,
        private array $currencyIds,
        private array $snippetSetIds,
    ) {
    }

    public function languageId(string $code): string
    {
        return $this->languageIds[$code];
    }

    public function marketLanguageId(Market $market): string
    {
        return $this->marketLanguageIds[$market->domain()];
    }

    public function currencyId(string $code): string
    {
        return $this->currencyIds[$code];
    }

    public function snippetSetId(string $code): string
    {
        return $this->snippetSetIds[$code];
    }
}
