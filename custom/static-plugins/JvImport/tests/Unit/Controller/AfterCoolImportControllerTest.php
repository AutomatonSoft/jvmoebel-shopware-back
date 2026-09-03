<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Controller;

use Jv\Import\Controller\AfterCoolImportController;
use Jv\Import\Integration\AfterCool\Exception\AfterCoolApiException;
use Jv\Import\Service\AfterCool\AfterCoolProductPreviewService;
use Jv\Import\Service\AfterCool\Contract\AfterCoolImportRunStore;
use Jv\Import\Service\AfterCool\Contract\AfterCoolProductSourceInterface;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductPageMappingResult;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductPreviewItem;
use Jv\Import\Service\AfterCool\ListAfterCoolFactoriesService;
use Jv\Import\Service\AfterCool\StartAfterCoolImportService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Messenger\MessageBusInterface;

final class AfterCoolImportControllerTest extends TestCase
{
    public function testProductsReturnsPreviewPaginationAndCuratedRows(): void
    {
        $source = $this->source(new AfterCoolProductPageMappingResult([], [], [new AfterCoolProductPreviewItem('900001', '900001', '4260174423463', 'Sofa', null, 10.0, 1, null, null, null, null, null, null, [], true, [])], 102, 50, true));

        $response = $this->controller($source)->products(Request::create('/api/_action/jv-import/aftercool/products', 'GET', ['factoryId' => '504034', 'limit' => '25', 'offset' => '50', 'q' => 'sofa']));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([
            'data' => [['productId' => '900001', 'artikelnummer' => '900001', 'ean' => '4260174423463', 'name' => 'Sofa', 'manufacturer' => null, 'price' => 10.0, 'stock' => 1, 'dimensions' => null, 'weight' => null, 'previewImage' => null, 'updatedAt' => null, 'sourceFile' => null, 'sourceKind' => null, 'description' => null, 'mediaUrls' => [], 'importable' => true, 'issues' => []]],
            'total' => 102,
            'limit' => 25,
            'offset' => 50,
            'hasMore' => true,
        ], json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testProductsRejectsAnInvalidFactoryId(): void
    {
        $response = $this->controller($this->source())->products(Request::create('/api/_action/jv-import/aftercool/products', 'GET', ['factoryId' => '0']));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('invalid_factory_id', json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)['errors'][0]['code']);
    }

    public function testProductsReturnsASafeGatewayErrorForAftercoolFailure(): void
    {
        $response = $this->controller($this->source(null, AfterCoolApiException::transport(new \RuntimeException('secret'))))->products(Request::create('/api/_action/jv-import/aftercool/products', 'GET', ['factoryId' => '504034']));

        self::assertSame(502, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('aftercool_transport_error', $payload['errors'][0]['code']);
        self::assertSame('Aftercool products could not be loaded.', $payload['errors'][0]['detail']);
    }

    private function controller(AfterCoolProductSourceInterface $source): AfterCoolImportController
    {
        return new AfterCoolImportController(
            new ListAfterCoolFactoriesService($source),
            new StartAfterCoolImportService($source, $this->createMock(AfterCoolImportRunStore::class), $this->createMock(MessageBusInterface::class), new LockFactory(new FlockStore(sys_get_temp_dir()))),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            new AfterCoolProductPreviewService($source),
        );
    }

    private function source(?AfterCoolProductPageMappingResult $page = null, ?\Throwable $failure = null): AfterCoolProductSourceInterface
    {
        return new class($page, $failure) implements AfterCoolProductSourceInterface {
            public function __construct(private readonly ?AfterCoolProductPageMappingResult $page, private readonly ?\Throwable $failure)
            {
            }

            public function getFactories(): array
            {
                return [];
            }

            public function getProductPage(
                int $factoryId,
                int $offset,
                int $limit = 100,
                ?string $query = null,
            ): AfterCoolProductPageMappingResult {
                if (null !== $this->failure) {
                    throw $this->failure;
                }

                return $this->page ?? new AfterCoolProductPageMappingResult([], [], [], 0, $offset, false);
            }
        };
    }
}
