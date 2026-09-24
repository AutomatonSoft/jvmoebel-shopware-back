<?php declare(strict_types=1);

namespace Jv\Seo\Service\Sitemap\Dto;

final readonly class SitemapArtifact
{
    public function __construct(
        public string $publicPath,
        public string $sourcePath,
        public string $contentType,
        public string $sha256,
        public int $size,
    ) {
        if (!str_ends_with($this->publicPath, '.xml.gz') || str_contains($this->publicPath, '/') || str_contains($this->publicPath, '\\')) {
            throw new \InvalidArgumentException('Sitemap artifact path must be a gzip XML filename.');
        }

        if (!preg_match('/^[a-f0-9]{64}$/', $this->sha256) || $this->size < 1) {
            throw new \InvalidArgumentException('Sitemap artifact metadata is invalid.');
        }

        if ('' === $this->sourcePath || str_starts_with($this->sourcePath, '/') || str_contains($this->sourcePath, '\\') || str_contains($this->sourcePath, '..')) {
            throw new \InvalidArgumentException('Sitemap artifact source path is invalid.');
        }
    }

    /** @return array{publicPath: string, sourcePath: string, contentType: string, sha256: string, size: int} */
    public function toArray(): array
    {
        return [
            'publicPath' => $this->publicPath,
            'sourcePath' => $this->sourcePath,
            'contentType' => $this->contentType,
            'sha256' => $this->sha256,
            'size' => $this->size,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (!is_string($data['publicPath'] ?? null)
            || !is_string($data['sourcePath'] ?? null)
            || !is_string($data['contentType'] ?? null)
            || !is_string($data['sha256'] ?? null)
            || !is_int($data['size'] ?? null)) {
            throw new \InvalidArgumentException('Persisted sitemap artifact is invalid.');
        }

        return new self($data['publicPath'], $data['sourcePath'], $data['contentType'], $data['sha256'], $data['size']);
    }
}
