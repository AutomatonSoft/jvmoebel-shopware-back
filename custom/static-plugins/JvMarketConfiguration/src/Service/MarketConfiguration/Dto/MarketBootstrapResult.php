<?php declare(strict_types=1);

namespace Jv\MarketConfiguration\Service\MarketConfiguration\Dto;

final readonly class MarketBootstrapResult
{
    public function __construct(
        public string $domain,
        public string $salesChannelId,
        public string $accessKey,
        public bool $created,
    ) {
    }

    public function status(): string
    {
        return $this->created ? 'created' : 'updated';
    }

    /**
     * @return array{domain: string, salesChannelId: string, accessKey: string, status: string}
     */
    public function toArray(): array
    {
        return [
            'domain' => $this->domain,
            'salesChannelId' => $this->salesChannelId,
            'accessKey' => $this->accessKey,
            'status' => $this->status(),
        ];
    }
}
