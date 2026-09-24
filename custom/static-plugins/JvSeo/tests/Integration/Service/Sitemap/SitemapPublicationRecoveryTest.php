<?php declare(strict_types=1);

namespace Jv\Seo\Tests\Integration\Service\Sitemap;

use Jv\Seo\Service\Sitemap\Contract\SitemapPublisherInterface;
use Jv\Seo\Service\Sitemap\CoordinatedSitemapExporter;
use Jv\Seo\Service\Sitemap\Dto\PublicationResult;
use Jv\Seo\Service\Sitemap\Dto\SitemapArtifact;
use Jv\Seo\Service\Sitemap\Dto\SitemapExport;
use Jv\Seo\Service\Sitemap\EligibleSitemapSalesChannels;
use Jv\Seo\Service\Sitemap\Exception\SitemapPublicationException;
use Jv\Seo\Service\Sitemap\GenerateAndPublishSitemapService;
use Jv\Seo\Service\Sitemap\SitemapArtifactCollector;
use Jv\Seo\Service\Sitemap\SitemapExportRunStore;
use Jv\Seo\Service\Sitemap\SitemapGenerationLock;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Shopware\Core\Content\Sitemap\Service\SitemapListerInterface;
use Shopware\Core\Content\Sitemap\SitemapException;
use Shopware\Core\Content\Sitemap\Struct\SitemapGenerationResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

final class SitemapPublicationRecoveryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function testRedeliveryReusesThePersistedImmutablePublicationPlan(): void
    {
        $context = Context::createDefaultContext();
        $store = $this->runStore();
        $runId = $store->createPending(null, 'system', $context);
        $export = $this->export($runId, 'one');
        $store->savePublicationPlan($runId, [$export], $context);
        $publisher = new RecordingSitemapPublisher([1]);
        $service = $this->service($publisher, $store);

        $run = $store->get($runId, $context);
        self::assertNotNull($run);
        try {
            $service->execute($run, $context);
            self::fail('The first delivery must simulate a worker interruption after remote commit.');
        } catch (SitemapPublicationException) {
        }

        $run = $store->get($runId, $context);
        self::assertNotNull($run);
        self::assertNull($run->getPublicationResult());
        $results = $service->execute($run, $context);

        self::assertCount(2, $publisher->exports);
        self::assertSame($publisher->exports[0], $publisher->exports[1]);
        self::assertSame($export->generatedAt->format(DATE_ATOM), $publisher->exports[1]['generatedAt']);
        self::assertSame($export->artifacts[0]->sha256, $publisher->exports[1]['artifacts'][0]['sha256']);
        self::assertCount(1, $results);
        self::assertCount(1, $store->get($runId, $context)?->getPublicationResult()['publications'] ?? []);
    }

    public function testCommittedDomainResultSurvivesALaterDomainFailure(): void
    {
        $context = Context::createDefaultContext();
        $store = $this->runStore();
        $runId = $store->createPending(null, 'system', $context);
        $first = $this->export($runId, 'one');
        $second = $this->export($runId, 'two');
        $store->savePublicationPlan($runId, [$first, $second], $context);
        $publisher = new RecordingSitemapPublisher([2]);
        $service = $this->service($publisher, $store);

        $run = $store->get($runId, $context);
        self::assertNotNull($run);
        try {
            $service->execute($run, $context);
            self::fail('The second domain publication must fail.');
        } catch (SitemapPublicationException $exception) {
            self::assertCount(1, $exception->publicationResults());
        }

        $persisted = $store->get($runId, $context)?->getPublicationResult()['publications'] ?? [];
        self::assertCount(1, $persisted);
        self::assertSame($first->publicationId, $persisted[0]['publicationId'] ?? null);
    }

    public function testStandardExporterUsesTheSameGenerationLock(): void
    {
        $context = $this->createSalesChannelContext();
        $lockFactory = new LockFactory(new InMemoryStore());
        $generationLock = new SitemapGenerationLock($lockFactory);
        $inner = $this->createMock(SitemapExporterInterface::class);
        $result = new SitemapGenerationResult(true, null, null, $context->getSalesChannelId(), $context->getLanguageId());
        $inner->expects(self::once())->method('generate')->with($context, false, null, null)->willReturn($result);
        $exporter = new CoordinatedSitemapExporter($inner, $generationLock);
        $heldLock = $generationLock->create($context->getSalesChannelId(), $context->getLanguageId());
        self::assertTrue($heldLock->acquire());

        $caught = null;
        try {
            $exporter->generate($context);
        } catch (\Throwable $exception) {
            $caught = $exception;
        } finally {
            $heldLock->release();
        }

        self::assertInstanceOf(SitemapException::class, $caught);
        self::assertSame($result, $exporter->generate($context));
    }

    private function runStore(): SitemapExportRunStore
    {
        return static::getContainer()->get(SitemapExportRunStore::class);
    }

    private function service(RecordingSitemapPublisher $publisher, SitemapExportRunStore $store): GenerateAndPublishSitemapService
    {
        $exporter = $this->createMock(SitemapExporterInterface::class);
        $exporter->expects(self::never())->method('generate');
        $collector = new SitemapArtifactCollector(
            $this->createMock(SitemapListerInterface::class),
            $this->createMock(FilesystemOperator::class),
        );

        return new GenerateAndPublishSitemapService(
            static::getContainer()->get(EligibleSitemapSalesChannels::class),
            static::getContainer()->get(SalesChannelContextFactory::class),
            $exporter,
            $collector,
            $publisher,
            new SitemapGenerationLock(new LockFactory(new InMemoryStore())),
            $store,
            new MockClock('2026-09-23T10:00:00+00:00'),
        );
    }

    private function export(string $runId, string $suffix): SitemapExport
    {
        $contents = 'gzip-'.$suffix;

        return new SitemapExport(
            Uuid::fromStringToHex($runId.'|'.$suffix),
            Uuid::fromStringToHex('sales-channel-'.$suffix),
            Uuid::fromStringToHex('language-'.$suffix),
            $suffix.'.example.test',
            new \DateTimeImmutable('2026-09-23T09:00:00+00:00'),
            [new SitemapArtifact('sitemap-'.$suffix.'.xml.gz', 'jv-seo-publications/'.$runId.'/'.$suffix.'/sitemap.xml.gz', 'application/gzip', hash('sha256', $contents), strlen($contents))],
        );
    }
}

final class RecordingSitemapPublisher implements SitemapPublisherInterface
{
    /** @var list<array<string, mixed>> */
    public array $exports = [];
    private int $call = 0;

    /** @param list<int> $failingCalls */
    public function __construct(private readonly array $failingCalls)
    {
    }

    public function publish(SitemapExport $export): PublicationResult
    {
        ++$this->call;
        $this->exports[] = $export->toArray();
        if (in_array($this->call, $this->failingCalls, true)) {
            throw new SitemapPublicationException('simulated_worker_interruption', true);
        }

        return new PublicationResult($export->publicationId, 'version-'.$this->call, new \DateTimeImmutable('2026-09-23T10:00:00+00:00'), array_map(static fn (SitemapArtifact $artifact): string => $artifact->publicPath, $export->artifacts));
    }
}
