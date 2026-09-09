<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\AfterCool;

use Doctrine\DBAL\Connection;
use Jv\Import\Service\AfterCool\Media\LinkAfterCoolExternalMediaService;
use Jv\Import\Service\AfterCool\Media\ProcessAfterCoolStagedMediaService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Upload\MediaUploadService;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;

final class ProcessAfterCoolStagedMediaServiceTest extends TestCase
{
    public function testRepositoryFailureAfterClaimReturnsTheTaskToPendingAndRetryUsesTheSameRelation(): void
    {
        $context = Context::createDefaultContext();
        $runId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $taskId = Uuid::randomHex();
        $url = 'https://images.example.test/retry.jpg';
        $mediaId = Uuid::fromStringToHex('jvmoebel.aftercool.media.'.$url);
        $stage = new \ArrayObject(['status' => 'pending']);
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnCallback(
            static fn (): array => 'pending' === $stage['status'] ? [[
                'id' => Uuid::fromHexToBytes($taskId),
                'product_id' => Uuid::fromHexToBytes($productId),
                'source_product_id' => 'aftercool-source',
                'url' => $url,
                'cover_candidate' => 1,
            ]] : [],
        );
        $connection->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $parameters = []) use ($stage): int {
                if (str_contains($sql, "SET `status` = 'pending'") && 'processing' === $stage['status']) {
                    $stage['status'] = 'pending';
                }
                if (str_contains($sql, 'WHERE `id` = :id AND `status` = :pending') && 'pending' === $stage['status']) {
                    $stage['status'] = 'processing';
                }
                if (str_contains($sql, 'WHERE `id` = :id') && isset($parameters['status'])) {
                    $stage['status'] = $parameters['status'];
                }

                return 1;
            },
        );
        $mediaUpload = $this->getMockBuilder(MediaUploadService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['linkURL'])
            ->getMock();
        $mediaUpload->expects(self::exactly(2))->method('linkURL')->willReturn($mediaId);
        $product = new ProductEntity();
        $product->setId($productId);
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            'product',
            1,
            new ProductCollection([$product]),
            null,
            new Criteria([$productId]),
            $context,
        ));
        $payloads = [];
        $repository->expects(self::exactly(2))->method('update')->willReturnCallback(
            static function (array $payload) use (&$payloads, $context): EntityWrittenContainerEvent {
                $payloads[] = $payload;
                if (1 === count($payloads)) {
                    throw new \RuntimeException('database temporarily unavailable');
                }

                return EntityWrittenContainerEvent::createWithWrittenEvents([], $context, []);
            },
        );
        $service = new ProcessAfterCoolStagedMediaService(
            $connection,
            new LinkAfterCoolExternalMediaService($mediaUpload),
            $repository,
        );

        try {
            $service->process($runId, 0, $context);
            self::fail('The repository failure must reach Messenger retry handling.');
        } catch (\RuntimeException $exception) {
            self::assertSame('database temporarily unavailable', $exception->getMessage());
        }
        self::assertSame('pending', $stage['status'], 'A retry must be able to claim the task again.');

        $service->process($runId, 0, $context);

        self::assertSame('completed', $stage['status']);
        self::assertSame($payloads[0][0]['media'][0]['id'], $payloads[1][0]['media'][0]['id']);
        self::assertSame($payloads[0][0]['media'][0]['mediaId'], $payloads[1][0]['media'][0]['mediaId']);
    }
}
