<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\AfterCool;

use Doctrine\DBAL\Connection;
use Jv\Import\Core\Content\AfterCoolImportRun\AfterCoolImportRunCollection;
use Jv\Import\Core\Content\AfterCoolImportRun\AfterCoolImportRunEntity;
use Jv\Import\Integration\AfterCool\AfterCoolApiClient;
use Jv\Import\Integration\AfterCool\AfterCoolResponseNormalizer;
use Jv\Import\Integration\AfterCool\Exception\AfterCoolApiException;
use Jv\Import\Message\AfterCoolImportPageMessage;
use Jv\Import\Service\AfterCool\AfterCoolExternalMediaLinkService;
use Jv\Import\Service\AfterCool\AfterCoolImportPageProcessorService;
use Jv\Import\Service\AfterCool\AfterCoolImportRunStoreService;
use Jv\Import\Service\ProductImport\ProductImportIdentity;
use Jv\Import\Service\ProductImport\ResolveDefaultProductTaxService;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Jv\MarketConfiguration\Service\MarketConfiguration\PrepareMarketReferenceDataService;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\Upload\MediaUploadService;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Bridge\Redis\Transport\Connection as RedisConnection;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransport;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Worker;

final class AfterCoolImportWorkflowTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;

    private const int FACTORY_ID = 912345;

    /** @var list<array<string, mixed>> */
    private array $items = [];

    private int $productRequests = 0;

    private int $productHttpStatus = 200;

    #[Before(100)]
    public function bootIsolatedServiceContainer(): void
    {
        // Run before the transaction hooks so API substitution cannot leak between tests.
        KernelLifecycleManager::ensureKernelShutdown();
    }

    #[AfterClass]
    public static function discardSubstitutedApiClient(): void
    {
        KernelLifecycleManager::ensureKernelShutdown();
    }

    public function testHttpStartRedisWorkerProgressAndRepeatedImportUseTheRegisteredServices(): void
    {
        $context = $this->prepare(array_map($this->item(...), range(1, 101)));
        $transport = $this->resetTestTransport();
        $browser = $this->getBrowser();
        $browser->jsonRequest('GET', '/api/_action/jv-import/aftercool/factories');
        self::assertSame(200, $browser->getResponse()->getStatusCode());
        self::assertSame(self::FACTORY_ID, $this->response()['data'][0]['id']);

        $browser->jsonRequest('POST', '/api/_action/jv-import/aftercool/runs', ['factoryId' => self::FACTORY_ID]);
        self::assertSame(202, $browser->getResponse()->getStatusCode());
        $runId = $this->response()['data']['id'];
        self::assertIsString($runId);
        self::assertSame(0, $this->productRequests, 'The HTTP start must not fetch products.');
        self::assertSame('queued', $this->loadRun($runId, $context)->getStatus());
        self::assertSame(0, $this->sourceProductCount());

        $this->consumePages($transport, 1);
        $browser->jsonRequest('GET', '/api/_action/jv-import/aftercool/runs/'.$runId);
        self::assertSame(200, $browser->getResponse()->getStatusCode());
        self::assertSame(100, $this->response()['data']['processed']);
        self::assertSame('running', $this->response()['data']['status']);
        $browser->jsonRequest('POST', '/api/_action/jv-import/aftercool/runs', ['factoryId' => self::FACTORY_ID]);
        self::assertSame(409, $browser->getResponse()->getStatusCode());

        $this->consumePages($transport, 1);
        $first = $this->loadRun($runId, $context);
        self::assertSame('completed', $first->getStatus());
        self::assertSame(101, $first->getCreated());
        self::assertSame(101, $this->sourceProductCount());
        self::assertSame(2, $this->productRequests);
        $productId = ProductImportIdentity::fromProductNumber($this->ean(1));
        $product = $this->product($productId, $context);
        self::assertSame($this->ean(1), $product->getProductNumber());
        self::assertFalse($product->getActive());
        self::assertSame(5, $product->getStock());

        $this->bus()->dispatch(new AfterCoolImportPageMessage($runId, 0));
        $this->consumePages($transport, 1);
        self::assertSame(101, $this->loadRun($runId, $context)->getProcessed());
        self::assertSame(2, $this->productRequests, 'Replay must not fetch or write the completed page.');

        $this->items[0]['row']['Menge'] = '27';
        $this->items[0]['row']['Startpreis'] = '238';
        $browser->jsonRequest('POST', '/api/_action/jv-import/aftercool/runs', ['factoryId' => self::FACTORY_ID]);
        self::assertSame(202, $browser->getResponse()->getStatusCode());
        $secondId = $this->response()['data']['id'];
        self::assertIsString($secondId);
        $this->consumePages($transport, 2);
        $browser->jsonRequest('GET', '/api/_action/jv-import/aftercool/runs/'.$secondId);
        self::assertSame('completed', $this->response()['data']['status']);
        self::assertSame(101, $this->response()['data']['updated']);
        self::assertSame(0, $this->response()['data']['created']);
        self::assertSame(101, $this->sourceProductCount());
        $updated = $this->product($productId, $context);
        self::assertSame(27, $updated->getStock());
        self::assertSame(238.0, $updated->getPrice()?->getCurrencyPrice(Defaults::CURRENCY, false)?->getGross());
    }

    public function testAllAdministrationEndpointsRequireImportExportPermission(): void
    {
        $this->prepare([]);
        $browser = $this->getBrowser(permissions: []);
        $runId = Uuid::randomHex();
        foreach (['factories', 'products?factoryId='.self::FACTORY_ID, 'runs/'.$runId, 'runs/'.$runId.'/errors'] as $path) {
            $browser->jsonRequest('GET', '/api/_action/jv-import/aftercool/'.$path);
            self::assertSame(403, $browser->getResponse()->getStatusCode(), $path);
        }
        $browser->jsonRequest('POST', '/api/_action/jv-import/aftercool/runs', ['factoryId' => self::FACTORY_ID]);
        self::assertSame(403, $browser->getResponse()->getStatusCode());
        self::assertSame(0, $this->productRequests);
    }

    public function testInvalidRowIsReportedAndTheFollowingProductIsCreated(): void
    {
        $context = $this->prepare([$this->item(1, ['Menge' => 'invalid']), $this->item(2)]);
        $runId = $this->process($context);
        $run = $this->loadRun($runId, $context);
        self::assertSame('completed_with_errors', $run->getStatus());
        self::assertSame(2, $run->getProcessed());
        self::assertSame(1, $run->getCreated());
        self::assertSame(1, $run->getFailed());
        self::assertSame(1, $this->sourceProductCount());
        $browser = $this->getBrowser();
        $browser->jsonRequest('GET', '/api/_action/jv-import/aftercool/runs/'.$runId.'/errors');
        self::assertSame(200, $browser->getResponse()->getStatusCode());
        self::assertSame('invalid_stock', $this->response()['data'][0]['code']);
        self::assertSame($this->ean(1), $this->response()['data'][0]['ean']);
        self::assertSame('workflow-1', $this->response()['data'][0]['productId']);
        self::assertArrayNotHasKey('row', $this->response()['data'][0]);
    }

    public function testTransientApiFailureDoesNotAdvanceTheCheckpointAndCanBeRetried(): void
    {
        $context = $this->prepare([$this->item(1)]);
        $store = static::getContainer()->get(AfterCoolImportRunStoreService::class);
        $runId = $store->createQueued(self::FACTORY_ID, 'Workflow factory', 'JV:lister:'.self::FACTORY_ID, $context);
        $processor = static::getContainer()->get(AfterCoolImportPageProcessorService::class);
        $this->productHttpStatus = 503;
        try {
            $processor->process($runId, 0, $context);
            self::fail('A transient API failure must reach Messenger rather than complete the page.');
        } catch (AfterCoolApiException $exception) {
            self::assertTrue($exception->isRetryable());
        }
        self::assertSame(0, $this->loadRun($runId, $context)->getNextOffset());
        self::assertSame(0, $this->loadRun($runId, $context)->getProcessed());
        self::assertSame(0, $this->sourceProductCount());
        $this->productHttpStatus = 200;
        $processor->process($runId, 0, $context);
        $processor->process($runId, 0, $context);
        self::assertSame('completed', $this->loadRun($runId, $context)->getStatus());
        self::assertSame(1, $this->loadRun($runId, $context)->getCreated());
        self::assertSame(1, $this->sourceProductCount());
    }

    public function testUpdatePreservesOtherTranslationsCategoriesPropertiesAndVariants(): void
    {
        $context = $this->prepare([$this->item(1)]);
        $id = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $optionId = Uuid::randomHex();
        $childId = Uuid::randomHex();
        $tax = static::getContainer()->get(ResolveDefaultProductTaxService::class)->execute();
        $this->products()->create([[
            'id' => $id,
            'productNumber' => $this->ean(1),
            'name' => 'Existing root name',
            'stock' => 1,
            'active' => true,
            'taxId' => $tax->id,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 84, 'linked' => false]],
            'categories' => [['id' => $categoryId, 'name' => 'Manually assigned category']],
            'properties' => [['id' => $optionId, 'name' => 'Manual value', 'group' => ['name' => 'Manual group']]],
            'translations' => [
                Market::Germany->languageId() => ['name' => 'Old German name', 'description' => '<p>Existing description</p>'],
                Market::UnitedKingdom->languageId() => ['name' => 'Keep English name', 'description' => '<p>Keep English description</p>'],
            ],
            'children' => [['id' => $childId, 'productNumber' => 'WORKFLOW-CHILD', 'stock' => 3]],
        ]], $context);
        $this->process($context);
        $criteria = (new Criteria([$id]))->addAssociation('translations')->addAssociation('categories')->addAssociation('properties')->addAssociation('children');
        $product = $this->products()->search($criteria, $context)->first();
        self::assertInstanceOf(ProductEntity::class, $product);
        self::assertTrue($product->getActive());
        self::assertTrue($product->getCategories()?->has($categoryId));
        self::assertTrue($product->getProperties()?->has($optionId));
        self::assertTrue($product->getChildren()?->has($childId));
        $translations = $product->getTranslations();
        self::assertNotNull($translations);
        $german = $translations->filterByLanguageId(Market::Germany->languageId())->first();
        $english = $translations->filterByLanguageId(Market::UnitedKingdom->languageId())->first();
        self::assertNotNull($german);
        self::assertNotNull($english);
        self::assertSame('Workflow product 1', $german->getName());
        self::assertSame('<p>Existing description</p>', $german->getDescription());
        self::assertSame('Keep English name', $english->getName());
        self::assertSame('<p>Keep English description</p>', $english->getDescription());
    }

    public function testInvalidMediaDoesNotCountOneProductTwice(): void
    {
        $context = $this->prepare([$this->item(1, ['GalleryURL' => 'javascript:alert(1)'])]);
        $run = $this->loadRun($this->process($context), $context);
        self::assertSame(1, $run->getCreated());
        self::assertSame(1, $run->getProcessed());
        self::assertSame(0, $run->getFailed());
        self::assertSame('completed_with_errors', $run->getStatus());
    }

    public function testErrorPaginationReturnsTheFullReportTotal(): void
    {
        $context = $this->prepare(array_map(fn (int $number): array => $this->item($number, ['Menge' => 'invalid']), range(1, 51)));
        $runId = $this->process($context);
        $browser = $this->getBrowser();
        $browser->jsonRequest('GET', '/api/_action/jv-import/aftercool/runs/'.$runId.'/errors?limit=50&offset=0');
        self::assertSame(200, $browser->getResponse()->getStatusCode());
        self::assertCount(50, $this->response()['data']);
        self::assertSame(51, $this->response()['total'], 'The UI must be able to navigate beyond the first 50 errors.');
        $firstIds = array_column($this->response()['data'], 'id');
        $browser->jsonRequest('GET', '/api/_action/jv-import/aftercool/runs/'.$runId.'/errors?limit=50&offset=50');
        self::assertCount(1, $this->response()['data']);
        self::assertSame(51, $this->response()['total']);
        self::assertNotContains($this->response()['data'][0]['id'], $firstIds);
    }

    public function testDelayedMessageCannotReviveFailedRun(): void
    {
        $context = $this->prepare([$this->item(1)]);
        $store = static::getContainer()->get(AfterCoolImportRunStoreService::class);
        $runId = $store->createQueued(self::FACTORY_ID, 'Workflow factory', 'JV:lister:'.self::FACTORY_ID, $context);
        $store->markFailed($runId, 'aftercool_http_503', 'Safe failure', $context);
        static::getContainer()->get(AfterCoolImportPageProcessorService::class)->process($runId, 0, $context);
        self::assertSame('failed', $this->loadRun($runId, $context)->getStatus());
        self::assertSame(0, $this->productRequests);
        self::assertSame(0, $this->sourceProductCount());
    }

    public function testSyncPreservesExistingOtherCurrencyPrices(): void
    {
        $context = $this->prepare([$this->item(1)]);
        $connection = static::getContainer()->get(Connection::class);
        $currencyId = $connection->fetchOne('SELECT LOWER(HEX(id)) FROM currency WHERE id <> ? LIMIT 1', [Uuid::fromHexToBytes(Defaults::CURRENCY)]);
        self::assertIsString($currencyId);
        $id = Uuid::randomHex();
        $tax = static::getContainer()->get(ResolveDefaultProductTaxService::class)->execute();
        $this->products()->create([[
            'id' => $id,
            'productNumber' => $this->ean(1),
            'name' => 'Existing product',
            'stock' => 1,
            'taxId' => $tax->id,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 84, 'linked' => false],
                ['currencyId' => $currencyId, 'gross' => 777, 'net' => 700, 'linked' => false],
            ],
        ]], $context);
        $this->process($context);
        $prices = $this->product($id, $context)->getPrice();
        self::assertNotNull($prices);
        self::assertCount(2, $prices);
        self::assertSame(777.0, $prices->getCurrencyPrice($currencyId, false)?->getGross());
        self::assertSame(119.0, $prices->getCurrencyPrice(Defaults::CURRENCY, false)?->getGross());
    }

    public function testExistingExternalMediaUsesTheIdReturnedByShopwareDeduplication(): void
    {
        $context = $this->prepare([]);
        $id = Uuid::randomHex();
        $url = 'https://images.example.test/existing-workflow.jpg';
        /** @var EntityRepository<MediaCollection> $media */
        $media = static::getContainer()->get('media.repository');
        $context->scope(Context::SYSTEM_SCOPE, static fn (Context $system) => $media->create([[
            'id' => $id,
            'path' => $url,
            'fileName' => 'existing-workflow',
            'fileExtension' => 'jpg',
            'mimeType' => 'image/jpeg',
            'fileSize' => 123,
            'private' => false,
        ]], $system));
        $service = new AfterCoolExternalMediaLinkService(static::getContainer()->get(MediaUploadService::class));
        $result = $service->link(Uuid::randomHex(), [$url], null, $context);
        self::assertSame([], $result->issues);
        self::assertSame($id, $result->productMedia[0]['mediaId']);
    }

    public function testConflictingSourceIdsDoNotLeaveUnlinkedProducts(): void
    {
        $second = $this->item(2);
        $second['product_id'] = 'workflow-1';
        $context = $this->prepare([$this->item(1), $second]);
        $run = $this->loadRun($this->process($context), $context);
        self::assertSame(2, $run->getProcessed());
        self::assertGreaterThan(0, $run->getSkipped() + $run->getFailed());
        $ids = [ProductImportIdentity::fromProductNumber($this->ean(1)), ProductImportIdentity::fromProductNumber($this->ean(2))];
        self::assertCount($this->sourceProductCount(), $this->products()->searchIds(new Criteria($ids), $context)->getIds());
    }

    /** @param list<array<string, mixed>> $items */
    private function prepare(array $items): Context
    {
        self::assertSame('shopware_test', static::getContainer()->get(Connection::class)->getDatabase());
        $this->items = $items;
        $this->productRequests = 0;
        $http = new MockHttpClient(function (string $method, string $url): MockResponse {
            $path = parse_url($url, PHP_URL_PATH);
            if ('/auth/login' === $path) {
                self::assertSame('POST', $method);

                return new MockResponse('{}', ['response_headers' => ['set-cookie: session=workflow-test; Path=/']]);
            }
            if ('/api/import/factories' === $path) {
                return new MockResponse(json_encode(['items' => [['id' => (string) self::FACTORY_ID, 'name' => 'Workflow factory']]], JSON_THROW_ON_ERROR));
            }
            self::assertSame('/api/products', $path);
            ++$this->productRequests;
            if (200 !== $this->productHttpStatus) {
                return new MockResponse('{}', ['http_code' => $this->productHttpStatus]);
            }
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            self::assertSame((string) self::FACTORY_ID, $query['factory_id']);
            self::assertSame('1', $query['include_row']);
            self::assertSame('JV', $query['account']);
            self::assertSame('lister', $query['dataset']);
            $limit = (int) $query['limit'];
            $offset = (int) $query['offset'];

            return new MockResponse(json_encode([
                'items' => array_slice($this->items, $offset, $limit),
                'total' => count($this->items),
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => $offset + $limit < count($this->items),
            ], JSON_THROW_ON_ERROR));
        });
        static::getContainer()->set(AfterCoolApiClient::class, new AfterCoolApiClient($http, new NullLogger(), new AfterCoolResponseNormalizer(), 'https://aftercool.example.test', 'test-user', 'test-password', 1.0));
        $context = Context::createDefaultContext();
        static::getContainer()->get(PrepareMarketReferenceDataService::class)->execute(Market::cases(), $context);

        return $context;
    }

    /** @param array<string, mixed> $row
     * @return array<string, mixed> */
    private function item(int $number, array $row = []): array
    {
        return [
            'account' => 'JV', 'dataset' => 'lister', 'factory_id' => (string) self::FACTORY_ID,
            'product_id' => 'workflow-'.$number, 'ean' => $this->ean($number),
            'artikelnummer' => 'article-'.$number, 'name' => 'Workflow product '.$number,
            'row_no' => $number, 'source_file' => 'workflow.csv', 'source_kind' => 'csv',
            'updated_at' => '2026-08-31T10:00:00+00:00',
            'row' => array_replace(['Startpreis' => '119', 'Menge' => '5'], $row),
        ];
    }

    private function ean(int $number): string
    {
        $base = (string) (990000000000 + $number);
        $sum = 0;
        for ($i = 0; $i < 12; ++$i) {
            $sum += (int) $base[$i] * (0 === $i % 2 ? 1 : 3);
        }

        return $base.((10 - $sum % 10) % 10);
    }

    private function process(Context $context): string
    {
        $runId = static::getContainer()->get(AfterCoolImportRunStoreService::class)->createQueued(self::FACTORY_ID, 'Workflow factory', 'JV:lister:'.self::FACTORY_ID, $context);
        static::getContainer()->get(AfterCoolImportPageProcessorService::class)->process($runId, 0, $context);

        return $runId;
    }

    private function loadRun(string $id, Context $context): AfterCoolImportRunEntity
    {
        /** @var EntityRepository<AfterCoolImportRunCollection> $repository */
        $repository = static::getContainer()->get('jv_aftercool_import_run.repository');
        $run = $repository->search(new Criteria([$id]), $context)->first();
        self::assertInstanceOf(AfterCoolImportRunEntity::class, $run);

        return $run;
    }

    /** @return EntityRepository<ProductCollection> */
    private function products(): EntityRepository
    {
        return static::getContainer()->get('product.repository');
    }

    private function product(string $id, Context $context): ProductEntity
    {
        $product = $this->products()->search(new Criteria([$id]), $context)->first();
        self::assertInstanceOf(ProductEntity::class, $product);

        return $product;
    }

    private function sourceProductCount(): int
    {
        return (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(DISTINCT p.id) FROM product p INNER JOIN jv_aftercool_product_source s ON s.product_id=p.id WHERE s.factory_id = ?', [self::FACTORY_ID]);
    }

    /** @return array<string, mixed> */
    private function response(): array
    {
        return json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function resetTestTransport(): RedisTransport
    {
        $dsn = getenv('MESSENGER_TRANSPORT_DSN');
        self::assertIsString($dsn);
        self::assertSame('/test_messages', parse_url($dsn, PHP_URL_PATH), 'Never consume or clean the development stream.');
        RedisConnection::fromDsn($dsn)->cleanup();
        $transport = static::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(RedisTransport::class, $transport);
        $transport->setup();

        return $transport;
    }

    private function bus(): MessageBusInterface
    {
        return static::getContainer()->get('messenger.default_bus');
    }

    private function consumePages(RedisTransport $transport, int $expected): void
    {
        $dispatcher = static::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $handled = 0;
        $worker = new Worker(['async' => $transport], $this->bus(), $dispatcher);
        $listener = static function (WorkerMessageHandledEvent $event) use (&$handled, $expected, $worker): void {
            if ($event->getEnvelope()->getMessage() instanceof AfterCoolImportPageMessage && ++$handled >= $expected) {
                $worker->stop();
            }
        };
        $dispatcher->addListener(WorkerMessageHandledEvent::class, $listener);
        try {
            $worker->run(['time_limit' => 15, 'sleep' => 1000]);
        } finally {
            $dispatcher->removeListener(WorkerMessageHandledEvent::class, $listener);
        }
        self::assertSame($expected, $handled, 'The registered Redis routing and handler must complete the requested pages.');
    }
}
