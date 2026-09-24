<?php declare(strict_types=1);

namespace Jv\Seo\Integration\NextRobots;

use Jv\Seo\Service\Robots\Contract\RobotsPublisherInterface;
use Jv\Seo\Service\Robots\Dto\RobotsPublication;
use Jv\Seo\Service\Robots\Dto\RobotsPublicationResult;
use Jv\Seo\Service\Robots\Exception\RobotsPublicationException;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class NextRobotsPublisher implements RobotsPublisherInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private FilesystemOperator $sitemapFilesystem,
        private string $endpoint,
        private string $secret,
        private float $timeout,
        private string $environment,
    ) {
    }

    public function publish(RobotsPublication $publication): RobotsPublicationResult
    {
        $this->assertConfiguration();
        $path = '/api/internal/sitemap-publications';
        $this->request('POST', $path, ['json' => [
            'publicationType' => 'robots',
            'publicationId' => $publication->publicationId,
            'environment' => $this->environment,
            'salesChannelId' => $publication->salesChannelId,
            'languageId' => $publication->languageId,
            'host' => $publication->host,
            'generatedAt' => $publication->generatedAt->format(DATE_ATOM),
            'artifacts' => [[
                'path' => 'robots.txt',
                'contentType' => RobotsPublication::CONTENT_TYPE,
                'sha256' => $publication->sha256,
                'size' => $publication->size,
            ]],
        ]]);

        try {
            $contents = $this->sitemapFilesystem->read($publication->sourcePath);
        } catch (FilesystemException $exception) {
            throw new RobotsPublicationException('robots_artifact_unreadable', false, $exception);
        }
        if (strlen($contents) !== $publication->size || hash('sha256', $contents) !== $publication->sha256) {
            throw new RobotsPublicationException('robots_artifact_checksum_mismatch', false);
        }

        $this->request('PUT', $path.'/'.$publication->publicationId.'/artifacts/robots.txt', [
            'body' => $contents,
            'headers' => [
                'Content-Type' => RobotsPublication::CONTENT_TYPE,
                'Content-Length' => (string) $publication->size,
                'X-Checksum-Sha256' => $publication->sha256,
            ],
        ]);
        $response = $this->request('POST', $path.'/'.$publication->publicationId.'/commit', ['json' => []]);
        try {
            $payload = $response->toArray(false);
        } catch (\Throwable $exception) {
            throw new RobotsPublicationException('robots_invalid_commit_response', false, $exception);
        }
        $destinationVersion = $payload['destinationVersion'] ?? null;
        if (!is_string($destinationVersion) || '' === $destinationVersion) {
            throw new RobotsPublicationException('robots_invalid_commit_response', false);
        }

        return new RobotsPublicationResult($publication->publicationId, $destinationVersion, new \DateTimeImmutable());
    }

    /** @param array<string, mixed> $options */
    private function request(string $method, string $path, array $options): ResponseInterface
    {
        $options = [...$options, 'timeout' => $this->timeout, 'headers' => [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->secret,
            ...($options['headers'] ?? []),
        ]];

        for ($attempt = 1; $attempt <= 3; ++$attempt) {
            try {
                $response = $this->httpClient->request($method, rtrim($this->endpoint, '/').$path, $options);
                $status = $response->getStatusCode();
            } catch (TransportExceptionInterface $exception) {
                if (3 === $attempt) {
                    throw new RobotsPublicationException('robots_transport_error', true, $exception);
                }

                continue;
            }
            if ($status >= 200 && $status < 300) {
                return $response;
            }
            if ((429 === $status || $status >= 500) && $attempt < 3) {
                continue;
            }

            throw new RobotsPublicationException('robots_http_'.$status, 429 === $status || $status >= 500);
        }

        throw new \LogicException('Robots publisher retry loop unexpectedly ended.');
    }

    private function assertConfiguration(): void
    {
        $parts = parse_url($this->endpoint);
        if (!is_array($parts) || !isset($parts['host']) || !in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            || '' === trim($this->secret) || '' === trim($this->environment) || $this->timeout <= 0) {
            throw new RobotsPublicationException('robots_publisher_configuration_invalid', false);
        }
    }
}
