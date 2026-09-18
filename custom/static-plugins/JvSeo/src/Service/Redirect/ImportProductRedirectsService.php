<?php declare(strict_types=1);

namespace Jv\Seo\Service\Redirect;

use Jv\Seo\Contract\ImportProductRedirectData;
use Jv\Seo\Contract\ImportProductRedirectsInterface;
use Jv\Seo\Contract\ProductRedirectImportResult;
use Jv\Seo\Core\Content\Redirect\RedirectCollection;
use Jv\Seo\Core\Content\Redirect\RedirectEntity;
use Jv\Seo\Core\Content\RedirectChannel\RedirectChannelCollection;
use Jv\Seo\Core\Content\RedirectChannel\RedirectChannelEntity;
use Jv\Seo\Core\Content\RedirectSource\RedirectSourceCollection;
use Jv\Seo\Core\Content\RedirectSource\RedirectSourceEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class ImportProductRedirectsService implements ImportProductRedirectsInterface
{
    /**
     * @param EntityRepository<RedirectCollection>        $redirectRepository
     * @param EntityRepository<RedirectChannelCollection> $channelRepository
     * @param EntityRepository<RedirectSourceCollection>  $sourceRepository
     */
    public function __construct(
        private EntityRepository $redirectRepository,
        private EntityRepository $channelRepository,
        private EntityRepository $sourceRepository,
        private UrlNormalizer $urlNormalizer,
    ) {
    }

    public function import(iterable $redirects, Context $context): ProductRedirectImportResult
    {
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $manualPreserved = 0;
        $conflicts = 0;
        $invalid = 0;
        $issues = [];

        foreach ($redirects as $data) {
            try {
                $outcome = $this->importOne($data, $context);
                match ($outcome) {
                    'created' => ++$created,
                    'updated' => ++$updated,
                    'unchanged' => ++$unchanged,
                    'manual_preserved' => ++$manualPreserved,
                    'conflict' => ++$conflicts,
                };
                if ('conflict' === $outcome) {
                    $issues[] = $this->issue($data, 'source_url_conflict', 'The source URL already belongs to another redirect target.');
                }
            } catch (\InvalidArgumentException $exception) {
                ++$invalid;
                $issues[] = $this->issue($data, 'invalid_redirect', $exception->getMessage());
            } catch (\Throwable) {
                ++$conflicts;
                $issues[] = $this->issue($data, 'write_conflict', 'The redirect could not be written because of a conflicting concurrent change.');
            }
        }

        return new ProductRedirectImportResult($created, $updated, $unchanged, $manualPreserved, $conflicts, $invalid, $issues);
    }

    private function importOne(ImportProductRedirectData $data, Context $context): string
    {
        if ('' === trim($data->sourceSystem) || '' === trim($data->sourceMarket) || '' === trim($data->sourceIdentifier)) {
            throw new \InvalidArgumentException('Source system, market and identifier are required.');
        }
        if (!Uuid::isValid($data->productId) || !Uuid::isValid($data->salesChannelId)) {
            throw new \InvalidArgumentException('Product and Sales Channel IDs must be valid UUIDs.');
        }

        $sourceUrl = $this->urlNormalizer->validate($data->sourceUrl);
        $sourceHost = preg_replace('/^www\./i', '', $this->urlNormalizer->host($sourceUrl));
        $marketHost = preg_replace('/^www\./i', '', strtolower(trim($data->sourceMarket)));
        if ($sourceHost !== $marketHost) {
            throw new \InvalidArgumentException('Source URL host does not match the source market.');
        }

        $rootId = Uuid::fromStringToHex('jv-seo.redirect.product.'.$data->productId);
        $root = $this->findProductRedirect($data->productId, $context);
        if ($root instanceof RedirectEntity) {
            $rootId = $root->getId();
        }

        $channelId = Uuid::fromStringToHex('jv-seo.redirect.channel.'.$rootId.'.'.$data->salesChannelId);
        $channel = $this->findChannel($rootId, $data->salesChannelId, $context);
        if ($channel instanceof RedirectChannelEntity && !$channel->isActive() && $channel->isManuallyModified()) {
            return 'manual_preserved';
        }
        if ($channel instanceof RedirectChannelEntity) {
            $channelId = $channel->getId();
        }

        $sourceHash = $this->urlNormalizer->hash($sourceUrl);
        $importKeyHash = hash('sha256', implode("\0", [
            trim($data->sourceSystem),
            $data->salesChannelId,
            trim($data->sourceIdentifier),
        ]));
        $byImportKey = $this->findSource('importKeyHash', $importKeyHash, $context);
        if ($byImportKey instanceof RedirectSourceEntity) {
            if ($byImportKey->isManuallyModified()) {
                return 'manual_preserved';
            }
            $existingChannel = $byImportKey->getChannel();
            $existingTarget = $existingChannel?->getRedirect();
            if (!$existingTarget instanceof RedirectEntity || $existingTarget->getProductId() !== $data->productId || $existingChannel->getSalesChannelId() !== $data->salesChannelId) {
                return 'conflict';
            }
            if ($byImportKey->isActive() && $byImportKey->getSourceUrlHash() === $sourceHash) {
                return 'unchanged';
            }
        }

        $collision = $this->findSource('activeSourceUrlHash', $sourceHash, $context);
        if ($collision instanceof RedirectSourceEntity && $collision->getId() !== $byImportKey?->getId()) {
            $collisionChannel = $collision->getChannel();
            $collisionTarget = $collisionChannel?->getRedirect();
            if ($collisionTarget instanceof RedirectEntity
                && $collisionTarget->getProductId() === $data->productId
                && $collisionChannel->getSalesChannelId() === $data->salesChannelId) {
                return 'unchanged';
            }

            return 'conflict';
        }

        if (!$root instanceof RedirectEntity) {
            $this->redirectRepository->create([[
                'id' => $rootId,
                'type' => RedirectType::Product->value,
                'productId' => $data->productId,
                'productVersionId' => Defaults::LIVE_VERSION,
            ]], $context);
        }
        if (!$channel instanceof RedirectChannelEntity) {
            $this->channelRepository->create([[
                'id' => $channelId,
                'redirectId' => $rootId,
                'salesChannelId' => $data->salesChannelId,
                'targetUrl' => null,
                'active' => true,
                'manuallyModified' => false,
            ]], $context);
        }

        $sourceId = $byImportKey?->getId() ?? Uuid::fromStringToHex('jv-seo.redirect.source.'.$importKeyHash);
        $payload = [[
            'id' => $sourceId,
            'redirectChannelId' => $channelId,
            'sourceUrl' => $sourceUrl,
            'sourceUrlHash' => $sourceHash,
            'activeSourceUrlHash' => $sourceHash,
            'origin' => 'import',
            'sourceSystem' => trim($data->sourceSystem),
            'sourceMarket' => trim($data->sourceMarket),
            'sourceIdentifier' => trim($data->sourceIdentifier),
            'importKeyHash' => $importKeyHash,
            'active' => true,
            'manuallyModified' => false,
        ]];

        if ($byImportKey instanceof RedirectSourceEntity) {
            $this->sourceRepository->update($payload, $context);

            return 'updated';
        }

        $this->sourceRepository->create($payload, $context);

        return 'created';
    }

    private function findProductRedirect(string $productId, Context $context): ?RedirectEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('type', RedirectType::Product->value))
            ->addFilter(new EqualsFilter('productId', $productId))
            ->setLimit(1);

        return $this->redirectRepository->search($criteria, $context)->first();
    }

    private function findChannel(string $redirectId, string $salesChannelId, Context $context): ?RedirectChannelEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('redirectId', $redirectId))
            ->addFilter(new EqualsFilter('salesChannelId', $salesChannelId))
            ->setLimit(1);

        return $this->channelRepository->search($criteria, $context)->first();
    }

    private function findSource(string $field, string $value, Context $context): ?RedirectSourceEntity
    {
        $criteria = (new Criteria())
            ->addAssociation('channel.redirect')
            ->addFilter(new EqualsFilter($field, $value))
            ->setLimit(1);

        return $this->sourceRepository->search($criteria, $context)->first();
    }

    /** @return array{sourceIdentifier: string, sourceUrl: string, code: string, message: string} */
    private function issue(ImportProductRedirectData $data, string $code, string $message): array
    {
        return [
            'sourceIdentifier' => $data->sourceIdentifier,
            'sourceUrl' => $data->sourceUrl,
            'code' => $code,
            'message' => $message,
        ];
    }
}
