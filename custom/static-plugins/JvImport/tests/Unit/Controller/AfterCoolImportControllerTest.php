<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Controller;

use Jv\Import\Controller\AfterCoolImportController;
use Jv\Import\Integration\AfterCool\AfterCoolApiClientInterface;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolProductPage;
use Jv\Import\Integration\AfterCool\Exception\AfterCoolApiException;
use Jv\Import\Service\AfterCool\Contract\AfterCoolImportRunStore;
use Jv\Import\Service\AfterCool\Contract\AfterCoolProductPreviewProviderInterface;
use Jv\Import\Service\AfterCool\Contract\AfterCoolProductSourceInterface;
use Jv\Import\Service\AfterCool\Dto\AfterCoolProductPageMappingResult;
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
        $preview = $this->createMock(AfterCoolProductPreviewProviderInterface::class);
        $preview->expects(self::once())->method('preview')->with(504034, 25, 50, 'sofa')->willReturn([
            'items' => [['productId' => '900001', 'name' => 'Sofa', 'importable' => true, 'issues' => []]],
            'total' => 102,
            'limit' => 25,
            'offset' => 50,
            'hasMore' => true,
        ]);

        $response = $this->controller($preview)->products(Request::create('/api/_action/jv-import/aftercool/products', 'GET', ['factoryId' => '504034', 'limit' => '25', 'offset' => '50', 'q' => 'sofa']));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([
            'data' => [['productId' => '900001', 'name' => 'Sofa', 'importable' => true, 'issues' => []],
            ],
            'total' => 102,
            'limit' => 25,
            'offset' => 50,
            'hasMore' => true,
        ], json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testProductsRejectsAnInvalidFactoryId(): void
    {
        $preview = $this->createMock(AfterCoolProductPreviewProviderInterface::class);
        $preview->expects(self::never())->method('preview');

        $response = $this->controller($preview)->products(Request::create('/api/_action/jv-import/aftercool/products', 'GET', ['factoryId' => '0']));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('invalid_factory_id', json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)['errors'][0]['code']);
    }

    public function testProductsReturnsASafeGatewayErrorForAftercoolFailure(): void
    {
        $preview = $this->createMock(AfterCoolProductPreviewProviderInterface::class);
        $preview->method('preview')->willThrowException(AfterCoolApiException::transport(new \RuntimeException('secret')));

        $response = $this->controller($preview)->products(Request::create('/api/_action/jv-import/aftercool/products', 'GET', ['factoryId' => '504034']));

        self::assertSame(502, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('aftercool_transport_error', $payload['errors'][0]['code']);
        self::assertSame('Aftercool products could not be loaded.', $payload['errors'][0]['detail']);
    }

    private function controller(AfterCoolProductPreviewProviderInterface $preview): AfterCoolImportController
    {
        $api = new class implements AfterCoolApiClientInterface {
            public function getFactories(): array
            {
                return [];
            }

            public function getProductPage(int $factoryId, int $offset): AfterCoolProductPage
            {
                throw new \LogicException('Not used by preview endpoint.');
            }
        };

        $source = new class implements AfterCoolProductSourceInterface {
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
                throw new \LogicException('Not used by controller preview tests.');
            }
        };

        return new AfterCoolImportController(
            $api,
            new StartAfterCoolImportService($source, $this->createMock(AfterCoolImportRunStore::class), $this->createMock(MessageBusInterface::class), new LockFactory(new FlockStore(sys_get_temp_dir()))),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $preview,
        );
    }
}
