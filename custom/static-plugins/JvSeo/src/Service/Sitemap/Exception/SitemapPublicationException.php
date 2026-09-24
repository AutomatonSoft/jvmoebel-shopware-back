<?php declare(strict_types=1);

namespace Jv\Seo\Service\Sitemap\Exception;

class SitemapPublicationException extends \RuntimeException
{
    /** @param list<array<string, mixed>> $publicationResults */
    public function __construct(
        private readonly string $safeCode,
        private readonly bool $retryable,
        ?\Throwable $previous = null,
        private readonly array $publicationResults = [],
    ) {
        parent::__construct('Sitemap publication failed: '.$safeCode, 0, $previous);
    }

    public function safeCode(): string
    {
        return $this->safeCode;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    /** @return list<array<string, mixed>> */
    public function publicationResults(): array
    {
        return $this->publicationResults;
    }
}
