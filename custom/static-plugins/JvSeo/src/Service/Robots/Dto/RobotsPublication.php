<?php declare(strict_types=1);

namespace Jv\Seo\Service\Robots\Dto;

use Shopware\Core\Framework\Uuid\Uuid;

final readonly class RobotsPublication
{
    public const CONTENT_TYPE = 'text/plain; charset=utf-8';

    public function __construct(
        public string $publicationId,
        public string $salesChannelId,
        public string $languageId,
        public string $host,
        public \DateTimeImmutable $generatedAt,
        public string $sourcePath,
        public string $sha256,
        public int $size,
    ) {
        if (!Uuid::isValid($this->publicationId) || !Uuid::isValid($this->salesChannelId) || !Uuid::isValid($this->languageId)
            || !preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/', $this->host) || str_contains($this->host, '..')
            || '' === $this->sourcePath || str_starts_with($this->sourcePath, '/') || str_contains($this->sourcePath, '\\') || str_contains($this->sourcePath, '..')
            || !preg_match('/^[a-f0-9]{64}$/', $this->sha256) || $this->size < 0 || $this->size > 32768) {
            throw new \InvalidArgumentException('Robots publication metadata is invalid.');
        }
    }

    /** @return array{publicationId: string, salesChannelId: string, languageId: string, host: string, generatedAt: string, sourcePath: string, sha256: string, size: int} */
    public function toArray(): array
    {
        return [
            'publicationId' => $this->publicationId,
            'salesChannelId' => $this->salesChannelId,
            'languageId' => $this->languageId,
            'host' => $this->host,
            'generatedAt' => $this->generatedAt->format(DATE_ATOM),
            'sourcePath' => $this->sourcePath,
            'sha256' => $this->sha256,
            'size' => $this->size,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        foreach (['publicationId', 'salesChannelId', 'languageId', 'host', 'generatedAt', 'sourcePath', 'sha256'] as $key) {
            if (!is_string($data[$key] ?? null)) {
                throw new \InvalidArgumentException('Persisted robots publication is invalid.');
            }
        }
        if (!is_int($data['size'] ?? null)) {
            throw new \InvalidArgumentException('Persisted robots publication size is invalid.');
        }

        try {
            $generatedAt = new \DateTimeImmutable($data['generatedAt']);
        } catch (\Throwable $exception) {
            throw new \InvalidArgumentException('Persisted robots generation time is invalid.', 0, $exception);
        }

        return new self(
            $data['publicationId'],
            $data['salesChannelId'],
            $data['languageId'],
            $data['host'],
            $generatedAt,
            $data['sourcePath'],
            $data['sha256'],
            $data['size'],
        );
    }
}
