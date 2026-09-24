<?php declare(strict_types=1);

namespace Jv\Seo\Service\Robots;

use Jv\Seo\Core\Content\RobotsPublicationRun\RobotsPublicationRunCollection;
use Jv\Seo\Core\Content\RobotsPublicationRun\RobotsPublicationRunEntity;
use Jv\Seo\Service\Robots\Dto\RobotsPublication;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class RobotsPublicationRunStore
{
    /** @param EntityRepository<RobotsPublicationRunCollection> $repository */
    public function __construct(private EntityRepository $repository)
    {
    }

    public function createPending(string $salesChannelId, string $content, string $initiator, Context $context): string
    {
        $id = Uuid::randomHex();
        $this->repository->create([[
            'id' => $id,
            'salesChannelId' => $salesChannelId,
            'content' => $content,
            'initiator' => $initiator,
            'status' => RobotsPublicationRunStatus::Pending->value,
        ]], $context);

        return $id;
    }

    public function get(string $id, Context $context): ?RobotsPublicationRunEntity
    {
        $run = $this->repository->search(new Criteria([$id]), $context)->first();

        return $run instanceof RobotsPublicationRunEntity ? $run : null;
    }

    public function getLatestForChannel(string $salesChannelId, Context $context): ?RobotsPublicationRunEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(1);
        $run = $this->repository->search($criteria, $context)->first();

        return $run instanceof RobotsPublicationRunEntity ? $run : null;
    }

    public function markRunning(RobotsPublicationRunEntity $run, Context $context): bool
    {
        if (RobotsPublicationRunStatus::tryFrom($run->getStatus())?->isTerminal()) {
            return false;
        }

        $this->repository->update([[
            'id' => $run->getId(),
            'status' => RobotsPublicationRunStatus::Running->value,
            'startedAt' => $run->getStartedAt() ?? new \DateTimeImmutable(),
            'safeFailureCode' => null,
            'safeFailureMessage' => null,
        ]], $context);

        return true;
    }

    /** @param list<RobotsPublication> $publications */
    public function savePublicationPlan(string $runId, array $publications, Context $context): void
    {
        $this->repository->update([[
            'id' => $runId,
            'publicationPlan' => ['publications' => array_map(static fn (RobotsPublication $publication): array => $publication->toArray(), $publications)],
        ]], $context);
    }

    /**
     * @param array<string, mixed> $publicationResult
     *
     * @return list<array<string, mixed>>
     */
    public function appendPublicationResult(string $runId, array $publicationResult, Context $context): array
    {
        $run = $this->get($runId, $context);
        if (!$run instanceof RobotsPublicationRunEntity) {
            throw new \RuntimeException('Robots publication run was not found while recording a result.');
        }

        $publications = $run->getPublicationResult()['publications'] ?? [];
        if (!is_array($publications)) {
            $publications = [];
        }

        $merged = [];
        $wasReplaced = false;
        foreach ($publications as $publication) {
            if (!is_array($publication)) {
                continue;
            }
            if (($publication['publicationId'] ?? null) === ($publicationResult['publicationId'] ?? null)) {
                $merged[] = $publicationResult;
                $wasReplaced = true;

                continue;
            }
            $merged[] = $publication;
        }
        if (!$wasReplaced) {
            $merged[] = $publicationResult;
        }

        $this->repository->update([['id' => $runId, 'publicationResult' => ['publications' => $merged]]], $context);

        return $merged;
    }

    /** @param list<array<string, mixed>> $publicationResults */
    public function markPublished(RobotsPublicationRunEntity $run, array $publicationResults, Context $context): void
    {
        $this->repository->update([[
            'id' => $run->getId(),
            'status' => RobotsPublicationRunStatus::Published->value,
            'publicationResult' => ['publications' => $publicationResults],
            'finishedAt' => new \DateTimeImmutable(),
            'safeFailureCode' => null,
            'safeFailureMessage' => null,
        ]], $context);
    }

    public function markFailed(RobotsPublicationRunEntity $run, string $safeCode, Context $context): void
    {
        $this->repository->update([[
            'id' => $run->getId(),
            'status' => RobotsPublicationRunStatus::Failed->value,
            'finishedAt' => new \DateTimeImmutable(),
            'safeFailureCode' => $safeCode,
            'safeFailureMessage' => 'Robots.txt publication could not be completed.',
        ]], $context);
    }
}
