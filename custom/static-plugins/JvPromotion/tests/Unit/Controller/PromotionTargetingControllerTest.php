<?php declare(strict_types=1);

namespace Jv\Promotion\Tests\Unit\Controller;

use Doctrine\DBAL\Connection;
use Jv\Promotion\Controller\Admin\PromotionTargetingController;
use Jv\Promotion\Service\Promotion\JvPromotionTargetInputParser;
use Jv\Promotion\Service\Query\GetPromotionTargetsService;
use Jv\Promotion\Service\Query\ListPromotionCollectionsService;
use Jv\Promotion\Service\Query\ListPromotionFactoriesService;
use Jv\Promotion\Service\Query\ListPromotionFactoryPrefixesService;
use Jv\Promotion\Service\Query\PreviewPromotionTargetsService;
use Jv\Promotion\Service\Query\SearchPromotionProductsService;
use Jv\Promotion\Service\Write\SyncJvPromotionService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

final class PromotionTargetingControllerTest extends TestCase
{
    public function testListCollectionsRejectsInvalidFactoryId(): void
    {
        $response = $this->controller()->listCollections(Request::create('/api/_action/jv-promotion/collections', 'GET', ['factoryId' => '0']));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('invalid_factory_id', json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)['errors'][0]['code']);
    }

    public function testPreviewReturnsWarningsAndItems(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['019abc']);
        $connection->method('fetchOne')->willReturn(1);
        $connection->method('fetchAllAssociative')->willReturn([[
            'product_id' => '019abc',
            'ean' => '4260174422190',
            'name' => 'Promo product',
            'factory_id' => 498371,
            'factory_name' => 'UK-GANASI',
            'stammartikel_id' => '175220799',
            'collection_name' => 'Sofa L6004B',
            'base_price_json' => json_encode([['gross' => 3539.0, 'net' => 2973.95, 'linked' => false]], JSON_THROW_ON_ERROR),
        ]]);

        $response = $this->controller(previewConnection: $connection)->preview(
            Request::create(
                '/api/_action/jv-promotion/preview',
                'POST',
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'targets' => [
                        ['type' => 'factory', 'factoryId' => 498371],
                        ['type' => 'invalid'],
                    ],
                    'discountPercent' => 15,
                ], JSON_THROW_ON_ERROR),
            ),
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $payload['total']);
        self::assertSame('4260174422190', $payload['items'][0]['ean']);
        self::assertNotEmpty($payload['warnings']);
    }

    public function testSyncReturnsBadRequestForInvalidDiscount(): void
    {
        $response = $this->controller()->sync(
            Request::create(
                '/api/_action/jv-promotion/sync',
                'POST',
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['name' => 'Promo', 'discountPercent' => 150, 'targets' => []], JSON_THROW_ON_ERROR),
            ),
            Context::createDefaultContext(),
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('invalid_payload', json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)['errors'][0]['code']);
    }

    public function testGetTargetsRequiresPromotionId(): void
    {
        $response = $this->controller()->getTargets(Request::create('/api/_action/jv-promotion/targets', 'GET'), Context::createDefaultContext());

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('invalid_promotion_id', json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)['errors'][0]['code']);
    }

    public function testListFactoriesReturnsData(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([[
                'id' => 498371,
                'name' => 'UK-GANASI',
                'product_count' => 100,
            ]]);

        $response = $this->controller(factoriesConnection: $connection)->listFactories(
            Request::create('/api/_action/jv-promotion/factories', 'GET', ['q' => 'GANASI']),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(498371, json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)['data'][0]['id']);
    }

    private function controller(
        ?Connection $factoriesConnection = null,
        ?Connection $previewConnection = null,
    ): PromotionTargetingController {
        $factoriesConnection ??= $this->createMock(Connection::class);
        $previewConnection ??= $this->createMock(Connection::class);

        return new PromotionTargetingController(
            new ListPromotionFactoriesService($factoriesConnection),
            new ListPromotionFactoryPrefixesService($this->createMock(Connection::class)),
            new ListPromotionCollectionsService($this->createMock(Connection::class)),
            new SearchPromotionProductsService(
                $this->createMock(Connection::class),
                new \Jv\Promotion\Service\Query\JvPromotionProductQueryService($this->createMock(Connection::class)),
            ),
            new PreviewPromotionTargetsService(new \Jv\Promotion\Service\Query\JvPromotionProductQueryService($previewConnection)),
            new SyncJvPromotionService(
                $this->createMock(\Shopware\Core\Framework\DataAbstractionLayer\EntityRepository::class),
                $this->createMock(\Shopware\Core\Framework\DataAbstractionLayer\EntityRepository::class),
                $this->createMock(\Shopware\Core\Framework\DataAbstractionLayer\EntityRepository::class),
                new JvPromotionTargetInputParser(),
                new \Jv\Promotion\Service\Promotion\JvPromotionRuleFactory(),
            ),
            new GetPromotionTargetsService($this->createMock(\Shopware\Core\Framework\DataAbstractionLayer\EntityRepository::class)),
            new JvPromotionTargetInputParser(),
        );
    }
}
