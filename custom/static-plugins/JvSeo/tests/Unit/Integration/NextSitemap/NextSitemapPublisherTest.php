<?php declare(strict_types=1);

namespace Jv\Seo\Tests\Unit\Integration\NextSitemap;

use Jv\Seo\Integration\NextSitemap\NextSitemapPublisher;
use Jv\Seo\Service\Sitemap\Dto\SitemapArtifact;
use Jv\Seo\Service\Sitemap\Dto\SitemapExport;
use Jv\Seo\Service\Sitemap\Exception\SitemapPublicationException;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class NextSitemapPublisherTest extends TestCase
{
    private const CONTENTS = 'gzip sitemap bytes';
    private const SOURCE_PATH = 'jv-seo-publications/run/publication/sitemap-one.xml.gz';

    public function testItCreatesUploadsAndCommitsAnArtifactUsingThePrivateContract(): void
    {
        $responses = [$this->response(201), $this->response(204), $this->response(200, ['destinationVersion' => 'version-one'])];
        $calls = [];
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::exactly(3))->method('request')->willReturnCallback(static function (string $method, string $url, array $options) use (&$calls, &$responses): ResponseInterface {
            $calls[] = [$method, $url, $options];

            return array_shift($responses) ?? throw new \LogicException('Unexpected HTTP request.');
        });
        $result = $this->publisher($client)->publish($this->export());
        self::assertSame('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $result->publicationId);
        self::assertSame('version-one', $result->destinationVersion);
        self::assertSame(['sitemap-one.xml.gz'], $result->publishedPaths);
        self::assertSame('POST', $calls[0][0]);
        self::assertSame('http://next-internal:3000/api/internal/sitemap-publications', $calls[0][1]);
        self::assertSame('Bearer private-publisher-secret', $calls[0][2]['headers']['Authorization']);
        self::assertSame('test', $calls[0][2]['json']['environment']);
        self::assertSame('PUT', $calls[1][0]);
        self::assertSame(self::CONTENTS, $calls[1][2]['body']);
        self::assertSame('POST', $calls[2][0]);
        self::assertSame('http://next-internal:3000/api/internal/sitemap-publications/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/commit', $calls[2][1]);
    }

    public function testItClassifiesAContractFailureAsPermanent(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::once())->method('request')->willReturn($this->response(422));
        try {
            $this->publisher($client)->publish($this->export());
            self::fail('A permanent ingestion failure must fail publication.');
        } catch (SitemapPublicationException $exception) {
            self::assertSame('sitemap_http_422', $exception->safeCode());
            self::assertFalse($exception->isRetryable());
            self::assertStringNotContainsString('private-publisher-secret', $exception->getMessage());
        }
    }

    public function testItRetriesATemporaryIngestionFailure(): void
    {
        $responses = [$this->response(503), $this->response(201), $this->response(204), $this->response(200, ['destinationVersion' => 'version-one'])];
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::exactly(4))->method('request')->willReturnCallback(static function () use (&$responses): ResponseInterface {
            $response = array_shift($responses);
            if (!$response instanceof ResponseInterface) {
                throw new \LogicException('Unexpected HTTP request.');
            }

            return $response;
        });
        self::assertSame('version-one', $this->publisher($client)->publish($this->export())->destinationVersion);
    }

    private function publisher(HttpClientInterface $client): NextSitemapPublisher
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('read')->with(self::SOURCE_PATH)->willReturn(self::CONTENTS);

        return new NextSitemapPublisher($client, $filesystem, 'http://next-internal:3000', 'private-publisher-secret', 2.5, 'test');
    }

    private function export(): SitemapExport
    {
        return new SitemapExport('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'cccccccccccccccccccccccccccccccc', 'www.jvmoebel.de', new \DateTimeImmutable('2026-09-17T10:00:00+00:00'), [new SitemapArtifact('sitemap-one.xml.gz', self::SOURCE_PATH, 'application/gzip', hash('sha256', self::CONTENTS), strlen(self::CONTENTS))]);
    }

    /** @param array<string,mixed> $payload */
    private function response(int $status, array $payload = []): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('toArray')->with(false)->willReturn($payload);

        return $response;
    }
}
