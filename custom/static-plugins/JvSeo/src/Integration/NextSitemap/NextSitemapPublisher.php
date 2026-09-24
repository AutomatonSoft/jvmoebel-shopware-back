<?php declare(strict_types=1);

namespace Jv\Seo\Integration\NextSitemap;

use Jv\Seo\Service\Sitemap\Contract\SitemapPublisherInterface;
use Jv\Seo\Service\Sitemap\Dto\PublicationResult;
use Jv\Seo\Service\Sitemap\Dto\SitemapArtifact;
use Jv\Seo\Service\Sitemap\Dto\SitemapExport;
use Jv\Seo\Service\Sitemap\Exception\SitemapPublicationException;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class NextSitemapPublisher implements SitemapPublisherInterface
{
    public function __construct(private HttpClientInterface $httpClient, private FilesystemOperator $sitemapFilesystem, private string $endpoint, private string $secret, private float $timeout, private string $environment)
    {
    }

    public function publish(SitemapExport $export): PublicationResult
    {
        $this->assertConfiguration();
        $basePath = '/api/internal/sitemap-publications';
        $this->request('POST', $basePath, ['json' => ['publicationId' => $export->publicationId, 'environment' => $this->environment, 'salesChannelId' => $export->salesChannelId, 'languageId' => $export->languageId, 'host' => $export->host, 'generatedAt' => $export->generatedAt->format(DATE_ATOM), 'artifacts' => array_map(static fn (SitemapArtifact $artifact): array => ['path' => $artifact->publicPath, 'contentType' => $artifact->contentType, 'sha256' => $artifact->sha256, 'size' => $artifact->size], $export->artifacts)]]);
        foreach ($export->artifacts as $artifact) {
            try {
                $contents = $this->sitemapFilesystem->read($artifact->sourcePath);
            } catch (FilesystemException $exception) {
                throw new SitemapPublicationException('sitemap_artifact_unreadable', false, $exception);
            }
            $this->request('PUT', $basePath.'/'.$export->publicationId.'/artifacts/'.rawurlencode($artifact->publicPath), ['body' => $contents, 'headers' => ['Content-Type' => $artifact->contentType, 'Content-Length' => (string) $artifact->size, 'X-Checksum-Sha256' => $artifact->sha256]]);
        }
        $payload = $this->payload($this->request('POST', $basePath.'/'.$export->publicationId.'/commit', ['json' => []]));
        $destinationVersion = $payload['destinationVersion'] ?? null;
        if (!is_string($destinationVersion) || '' === $destinationVersion) {
            throw new SitemapPublicationException('sitemap_invalid_commit_response', false);
        }

        return new PublicationResult($export->publicationId, $destinationVersion, new \DateTimeImmutable(), array_map(static fn (SitemapArtifact $artifact): string => $artifact->publicPath, $export->artifacts));
    }

    /** @param array<string,mixed> $options */
    private function request(string $method, string $path, array $options): ResponseInterface
    {
        $options = [...$options, 'timeout' => $this->timeout, 'headers' => ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->secret, ...($options['headers'] ?? [])]];
        for ($attempt = 1; $attempt <= 3; ++$attempt) {
            try {
                $response = $this->httpClient->request($method, rtrim($this->endpoint, '/').$path, $options);
                $status = $response->getStatusCode();
            } catch (TransportExceptionInterface $exception) {
                if (3 === $attempt) {
                    throw new SitemapPublicationException('sitemap_transport_error', true, $exception);
                }

                continue;
            }
            if ($status >= 200 && $status < 300) {
                return $response;
            }
            if ((429 === $status || $status >= 500) && $attempt < 3) {
                continue;
            }
            throw new SitemapPublicationException('sitemap_http_'.$status, 429 === $status || $status >= 500);
        }
        throw new \LogicException('Sitemap publisher retry loop unexpectedly ended.');
    }

    /** @return array<string,mixed> */
    private function payload(ResponseInterface $response): array
    {
        try {
            $payload = $response->toArray(false);
        } catch (\Throwable $exception) {
            throw new SitemapPublicationException('sitemap_invalid_commit_response', false, $exception);
        }

        return $payload;
    }

    private function assertConfiguration(): void
    {
        $parts = parse_url($this->endpoint);
        if (!is_array($parts) || !isset($parts['host']) || !in_array($parts['scheme'] ?? null, ['http', 'https'], true) || '' === trim($this->secret) || '' === trim($this->environment) || $this->timeout <= 0) {
            throw new SitemapPublicationException('sitemap_publisher_configuration_invalid', false);
        }
    }
}
