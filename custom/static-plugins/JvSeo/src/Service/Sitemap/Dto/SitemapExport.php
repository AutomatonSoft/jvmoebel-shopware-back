<?php declare(strict_types=1);

namespace Jv\Seo\Service\Sitemap\Dto;

use Shopware\Core\Framework\Uuid\Uuid;

final readonly class SitemapExport
{
    /** @param list<SitemapArtifact> $artifacts */
    public function __construct(
        public string $publicationId,
        public string $salesChannelId,
        public string $languageId,
        public string $host,
        public \DateTimeImmutable $generatedAt,
        public array $artifacts,
    ) {
        if (!Uuid::isValid($this->publicationId) || !Uuid::isValid($this->salesChannelId) || !Uuid::isValid($this->languageId) || '' === $this->host || [] === $this->artifacts) {
            throw new \InvalidArgumentException('Sitemap export scope is invalid.');
        }
    }

    /** @return array{publicationId: string, salesChannelId: string, languageId: string, host: string, generatedAt: string, artifacts: list<array{publicPath: string, sourcePath: string, contentType: string, sha256: string, size: int}>} */
    public function toArray(): array
    {
        return [
            'publicationId' => $this->publicationId,
            'salesChannelId' => $this->salesChannelId,
            'languageId' => $this->languageId,
            'host' => $this->host,
            'generatedAt' => $this->generatedAt->format(DATE_ATOM),
            'artifacts' => array_map(static fn (SitemapArtifact $artifact): array => $artifact->toArray(), $this->artifacts),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (!is_string($data['publicationId'] ?? null)
            || !is_string($data['salesChannelId'] ?? null)
            || !is_string($data['languageId'] ?? null)
            || !is_string($data['host'] ?? null)
            || !is_string($data['generatedAt'] ?? null)
            || !is_array($data['artifacts'] ?? null)) {
            throw new \InvalidArgumentException('Persisted sitemap export is invalid.');
        }

        try {
            $generatedAt = new \DateTimeImmutable($data['generatedAt']);
        } catch (\Throwable $exception) {
            throw new \InvalidArgumentException('Persisted sitemap generation time is invalid.', 0, $exception);
        }

        return new self(
            $data['publicationId'],
            $data['salesChannelId'],
            $data['languageId'],
            $data['host'],
            $generatedAt,
            array_values(array_map(static function (mixed $artifact): SitemapArtifact {
                if (!is_array($artifact)) {
                    throw new \InvalidArgumentException('Persisted sitemap artifact is invalid.');
                }

                return SitemapArtifact::fromArray($artifact);
            }, $data['artifacts'])),
        );
    }
}
