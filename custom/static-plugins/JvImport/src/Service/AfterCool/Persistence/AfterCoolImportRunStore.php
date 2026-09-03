<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Persistence;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Jv\Import\Core\Content\AfterCoolImportRun\AfterCoolImportRunCollection;
use Jv\Import\Service\AfterCool\Exception\AfterCoolFactoryImportAlreadyRunningException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

readonly class AfterCoolImportRunStore
{
    /** @param EntityRepository<AfterCoolImportRunCollection> $runRepository */
    public function __construct(private EntityRepository $runRepository)
    {
    }

    public function createQueued(int $factoryId, string $factoryName, string $activeFactoryKey, Context $context): string
    {
        $id = Uuid::randomHex();
        try {
            $this->runRepository->create([[
                'id' => $id,
                'account' => 'JV',
                'dataset' => 'lister',
                'factoryId' => $factoryId,
                'factoryName' => $factoryName,
                'status' => 'queued',
                'nextOffset' => 0,
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'activeFactoryKey' => $activeFactoryKey,
                'startedAt' => new \DateTimeImmutable(),
            ]], $context);
        } catch (UniqueConstraintViolationException $exception) {
            if (!str_contains($exception->getMessage(), 'uniq.jv_aftercool_import_run.active_factory')) {
                throw $exception;
            }

            throw new AfterCoolFactoryImportAlreadyRunningException($factoryId);
        }

        return $id;
    }

    public function markFailed(string $runId, string $safeCode, string $safeMessage, Context $context): void
    {
        $this->runRepository->update([[
            'id' => $runId,
            'status' => 'failed',
            'activeFactoryKey' => null,
            'finishedAt' => new \DateTimeImmutable(),
            'safeFailureCode' => $safeCode,
            'safeFailureMessage' => $safeMessage,
        ]], $context);
    }
}
