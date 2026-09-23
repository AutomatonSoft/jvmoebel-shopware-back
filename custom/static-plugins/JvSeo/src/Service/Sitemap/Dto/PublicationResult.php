<?php declare(strict_types=1);

namespace Jv\Seo\Service\Sitemap\Dto;

final readonly class PublicationResult
{
    /** @param list<string> $publishedPaths */
    public function __construct(
        public string $publicationId,
        public string $destinationVersion,
        public \DateTimeImmutable $publishedAt,
        public array $publishedPaths,
    ) {
    }

    /** @return array{publicationId: string, destinationVersion: string, publishedAt: string, publishedPaths: list<string>} */
    public function toArray(): array
    {
        return [
            'publicationId' => $this->publicationId,
            'destinationVersion' => $this->destinationVersion,
            'publishedAt' => $this->publishedAt->format(DATE_ATOM),
            'publishedPaths' => $this->publishedPaths,
        ];
    }
}
