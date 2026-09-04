<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Media;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Jv\Import\Service\AfterCool\Dto\AfterCoolSyncWriteFailure;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class ProcessAfterCoolStagedMediaService
{
    /** @param EntityRepository<ProductCollection> $productRepository */
    public function __construct(
        private Connection $connection,
        private LinkAfterCoolExternalMediaService $mediaLinks,
        private EntityRepository $productRepository,
    ) {
    }

    public function process(string $runId, int $offset, Context $context): void
    {
        $this->connection->executeStatement(
            "UPDATE `jv_aftercool_media_stage` SET `status` = 'pending', `updated_at` = NOW(3) WHERE `run_id` = :runId AND `offset` = :offset AND `status` = 'processing'",
            ['runId' => Uuid::fromHexToBytes($runId), 'offset' => $offset],
            ['runId' => ParameterType::BINARY],
        );
        $tasks = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT `id`, `product_id`, `source_product_id`, `url`, `cover_candidate`
                FROM `jv_aftercool_media_stage`
                WHERE `run_id` = :runId AND `offset` = :offset AND `status` = :status
                ORDER BY `product_id`, `position`
                SQL,
            ['runId' => Uuid::fromHexToBytes($runId), 'offset' => $offset, 'status' => 'pending'],
            ['runId' => ParameterType::BINARY],
        );
        foreach ($this->groupByProduct($tasks) as $productId => $productTasks) {
            $taskIds = array_column($productTasks, 'id');
            $this->claim($taskIds);
            try {
                $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
                $result = $this->mediaLinks->link(
                    $productId,
                    array_column($productTasks, 'url'),
                    $product instanceof ProductEntity ? $product->getCoverId() : null,
                    $context,
                );
                if ([] !== $result->productMedia) {
                    $payload = ['id' => $productId, 'media' => $result->productMedia];
                    if (null !== $result->coverId) {
                        $payload['coverId'] = $result->coverId;
                    }
                    $this->productRepository->update([$payload], $context);
                }
                if ([] !== $result->issues) {
                    $this->recordIssues($runId, $offset, $productTasks, $result->issues);
                    $this->complete($taskIds, 'failed');

                    continue;
                }
                $this->complete($taskIds, 'completed');
            } catch (\Throwable $exception) {
                $this->complete($taskIds, 'pending');

                throw $exception;
            }
        }
    }

    /**
     * @param list<array{id: string, product_id: string, source_product_id: string, url: string, cover_candidate: int|string}> $tasks
     *
     * @return array<string, list<array{id: string, product_id: string, source_product_id: string, url: string, cover_candidate: int|string}>>
     */
    private function groupByProduct(array $tasks): array
    {
        $grouped = [];
        foreach ($tasks as $task) {
            $grouped[bin2hex($task['product_id'])][] = $task;
        }

        return $grouped;
    }

    /** @param list<string> $taskIds */
    private function claim(array $taskIds): void
    {
        foreach ($taskIds as $taskId) {
            $this->connection->executeStatement(
                'UPDATE `jv_aftercool_media_stage` SET `status` = :status, `updated_at` = NOW(3) WHERE `id` = :id AND `status` = :pending',
                ['status' => 'processing', 'id' => $taskId, 'pending' => 'pending'],
                ['id' => ParameterType::BINARY],
            );
        }
    }

    /** @param list<string> $taskIds */
    private function complete(array $taskIds, string $status): void
    {
        foreach ($taskIds as $taskId) {
            $this->connection->executeStatement(
                'UPDATE `jv_aftercool_media_stage` SET `status` = :status, `updated_at` = NOW(3) WHERE `id` = :id',
                ['status' => $status, 'id' => $taskId],
                ['id' => ParameterType::BINARY],
            );
        }
    }

    /**
     * @param list<array{id: string, product_id: string, source_product_id: string, url: string, cover_candidate: int|string}> $tasks
     * @param list<AfterCoolSyncWriteFailure>                                                                                  $issues
     */
    private function recordIssues(string $runId, int $offset, array $tasks, array $issues): void
    {
        $factoryId = $this->connection->fetchOne(
            'SELECT `factory_id` FROM `jv_aftercool_import_run` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($runId)],
            ['id' => ParameterType::BINARY],
        );
        $taskByUrl = [];
        foreach ($tasks as $task) {
            $taskByUrl[$task['url']] = $task;
        }
        foreach ($issues as $issue) {
            $task = $taskByUrl[$issue->sourceProductId] ?? $tasks[0];
            $this->connection->insert('jv_aftercool_import_error', [
                'id' => Uuid::fromHexToBytes(Uuid::randomHex()),
                'run_id' => Uuid::fromHexToBytes($runId),
                'factory_id' => $factoryId,
                'product_id' => $task['source_product_id'],
                'offset' => $offset,
                'result' => 'failed',
                'code' => $issue->code,
                'message' => $issue->message,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
            ], [
                'id' => ParameterType::BINARY,
                'run_id' => ParameterType::BINARY,
            ]);
        }
        $this->connection->executeStatement(
            "UPDATE `jv_aftercool_import_run` SET `status` = 'completed_with_errors' WHERE `id` = :id AND `status` = 'completed'",
            ['id' => Uuid::fromHexToBytes($runId)],
            ['id' => ParameterType::BINARY],
        );
    }
}
