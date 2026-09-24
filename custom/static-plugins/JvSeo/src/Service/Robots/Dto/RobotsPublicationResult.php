<?php declare(strict_types=1);

namespace Jv\Seo\Service\Robots\Dto;

final readonly class RobotsPublicationResult
{
    public function __construct(
        public string $publicationId,
        public string $destinationVersion,
        public \DateTimeImmutable $publishedAt,
    ) {
    }

    /** @return array{publicationId: string, destinationVersion: string, publishedAt: string} */
    public function toArray(): array
    {
        return [
            'publicationId' => $this->publicationId,
            'destinationVersion' => $this->destinationVersion,
            'publishedAt' => $this->publishedAt->format(DATE_ATOM),
        ];
    }
}
