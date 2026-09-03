<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class AfterCoolStagedMediaProcessorService
{
    /** @param EntityRepository<ProductCollection> $productRepository */
    public function __construct(
        private Connection $connection,
        private AfterCoolExternalMediaLinkService $mediaLinks,
        private EntityRepository $productRepository,
    ) {
    }

    public function process(string $runId, int $offset, Context $context): void
    {
        $tasks = $this->connection->fetchAllAssociative(
            'SELECT `id`, `product_id`, `url` FROM `jv_aftercool_media_stage` WHERE `run_id` = :runId AND `offset` = :offset AND `status` = :status ORDER BY `product_id`, `position`',
            ['runId' => Uuid::fromHexToBytes($runId), 'offset' => $offset, 'status' => 'pending'],
        );
        foreach ($tasks as $task) {
            $productId = bin2hex($task['product_id']);
            $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
            $result = $this->mediaLinks->link($productId, [$task['url']], $product instanceof ProductEntity ? $product->getCoverId() : null, $context);
            if ([] !== $result->productMedia) {
                $payload = ['id' => $productId, 'media' => $result->productMedia];
                if (null !== $result->coverId) {
                    $payload['coverId'] = $result->coverId;
                }
                $this->productRepository->update([$payload], $context);
            }
            $this->connection->executeStatement('UPDATE `jv_aftercool_media_stage` SET `status` = :status, `updated_at` = NOW(3) WHERE `id` = :id', ['status' => 'completed', 'id' => $task['id']]);
        }
    }
}
