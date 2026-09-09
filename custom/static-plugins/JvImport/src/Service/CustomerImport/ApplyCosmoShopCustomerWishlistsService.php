<?php declare(strict_types=1);

namespace Jv\Import\Service\CustomerImport;

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerWishlistCsvReader;
use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerWishlistIdentity;
use Jv\Import\Service\CustomerImport\Dto\ApplyCosmoShopCustomerWishlistsResult;
use Jv\Import\Service\ProductImport\ProductImportIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlistProduct\CustomerWishlistProductCollection;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;

final readonly class ApplyCosmoShopCustomerWishlistsService
{
    private const int BATCH_SIZE = 250;

    /**
     * @param EntityRepository<CustomerCollection>                $customerRepository
     * @param EntityRepository<ProductCollection>                 $productRepository
     * @param EntityRepository<CustomerWishlistCollection>        $wishlistRepository
     * @param EntityRepository<CustomerWishlistProductCollection> $wishlistProductRepository
     */
    public function __construct(
        private CosmoShopCustomerWishlistCsvReader $reader,
        private EntityRepository $customerRepository,
        private EntityRepository $productRepository,
        private EntityRepository $wishlistRepository,
        private EntityRepository $wishlistProductRepository,
    ) {
    }

    public function execute(Market $market, string $file, bool $dryRun, Context $context): ApplyCosmoShopCustomerWishlistsResult
    {
        $records = $this->reader->read($file);
        $failed = 0;
        $guest = 0;
        $sourceOrphanProduct = 0;
        $duplicate = 0;
        $prepared = [];
        $seen = [];

        foreach ($records as $record) {
            if (!$record->isWellFormed || null === $record->sourceCustomerId) {
                ++$failed;
                continue;
            }
            if (0 === $record->sourceCustomerId) {
                ++$guest;
                continue;
            }
            if ('' === $record->productNumber) {
                ++$sourceOrphanProduct;
                continue;
            }

            $key = $record->sourceCustomerId."\0".$record->productNumber;
            if (isset($seen[$key])) {
                ++$duplicate;
                continue;
            }
            $seen[$key] = true;
            $prepared[] = [
                'customerId' => CosmoShopCustomerIdentity::customerId($market, $record->sourceCustomerId),
                'sourceCustomerId' => $record->sourceCustomerId,
                'productId' => ProductImportIdentity::fromProductNumber($record->productNumber),
            ];
        }

        $customers = $this->existingCustomerIds($market, array_column($prepared, 'customerId'), $context);
        $products = $this->existingProductIds(array_column($prepared, 'productId'), $context);
        $missingCustomer = 0;
        $missingProduct = 0;
        $ready = [];
        foreach ($prepared as $item) {
            if (!isset($customers[$item['customerId']])) {
                ++$missingCustomer;
                continue;
            }
            if (!isset($products[$item['productId']])) {
                ++$missingProduct;
                continue;
            }
            $ready[] = $item;
        }

        $wishlistByCustomer = $this->wishlists($market, array_column($ready, 'customerId'), $context);
        $wishlistPayloads = [];
        foreach ($ready as $item) {
            if (isset($wishlistByCustomer[$item['customerId']])) {
                continue;
            }
            $wishlistId = CosmoShopCustomerWishlistIdentity::wishlistId($market, $item['sourceCustomerId']);
            $wishlistByCustomer[$item['customerId']] = $wishlistId;
            $wishlistPayloads[] = ['id' => $wishlistId, 'customerId' => $item['customerId'], 'salesChannelId' => $market->salesChannelId()];
        }

        $relations = $this->existingRelations(array_values($wishlistByCustomer), $context);
        $relationPayloads = [];
        $existing = 0;
        foreach ($ready as $item) {
            $wishlistId = $wishlistByCustomer[$item['customerId']];
            $key = $wishlistId."\0".$item['productId'];
            if (isset($relations[$key])) {
                ++$existing;
                continue;
            }
            $relationPayloads[] = [
                'id' => CosmoShopCustomerWishlistIdentity::productRelationId($wishlistId, $item['productId']),
                'wishlistId' => $wishlistId,
                'productId' => $item['productId'],
                'productVersionId' => Defaults::LIVE_VERSION,
            ];
        }

        $written = 0;
        $exceptionClasses = [];
        if (!$dryRun) {
            [, $wishlistFailures, $wishlistClasses, $writtenWishlistIds] = $this->write($this->wishlistRepository, $wishlistPayloads, $context);
            if ([] !== $wishlistPayloads) {
                $newWishlistIds = array_fill_keys(array_column($wishlistPayloads, 'id'), true);
                $writtenWishlistIds = array_fill_keys($writtenWishlistIds, true);
                $relationPayloads = array_values(array_filter(
                    $relationPayloads,
                    static fn (array $payload): bool => !isset($newWishlistIds[$payload['wishlistId']]) || isset($writtenWishlistIds[$payload['wishlistId']]),
                ));
            }
            [$written, $relationFailures, $relationClasses] = $this->write($this->wishlistProductRepository, $relationPayloads, $context);
            $failed += $wishlistFailures + $relationFailures;
            $exceptionClasses = [...$wishlistClasses, ...$relationClasses];
        }

        return new ApplyCosmoShopCustomerWishlistsResult(count($records), count($ready), count($wishlistByCustomer), $written, $existing, $duplicate, $guest, $sourceOrphanProduct, $missingCustomer, $missingProduct, $failed, $exceptionClasses);
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, true>
     */
    private function existingCustomerIds(Market $market, array $ids, Context $context): array
    {
        $existing = [];
        foreach (array_chunk(array_values(array_unique($ids)), self::BATCH_SIZE) as $batch) {
            foreach ($this->customerRepository->search(new Criteria($batch), $context) as $customer) {
                if ($market->salesChannelId() === $customer->getSalesChannelId()) {
                    $existing[$customer->getId()] = true;
                }
            }
        }

        return $existing;
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, true>
     */
    private function existingProductIds(array $ids, Context $context): array
    {
        $existing = [];
        foreach (array_chunk(array_values(array_unique($ids)), self::BATCH_SIZE) as $batch) {
            foreach ($this->productRepository->search(new Criteria($batch), $context) as $product) {
                $existing[$product->getId()] = true;
            }
        }

        return $existing;
    }

    /**
     * @param list<string> $customerIds
     *
     * @return array<string, string>
     */
    private function wishlists(Market $market, array $customerIds, Context $context): array
    {
        $wishlists = [];
        foreach (array_chunk(array_values(array_unique($customerIds)), self::BATCH_SIZE) as $batch) {
            $criteria = (new Criteria())->addFilter(new EqualsAnyFilter('customerId', $batch))->addFilter(new EqualsFilter('salesChannelId', $market->salesChannelId()));
            foreach ($this->wishlistRepository->search($criteria, $context) as $wishlist) {
                $wishlists[$wishlist->getCustomerId()] = $wishlist->getId();
            }
        }

        return $wishlists;
    }

    /**
     * @param list<string> $wishlistIds
     *
     * @return array<string, true>
     */
    private function existingRelations(array $wishlistIds, Context $context): array
    {
        $relations = [];
        foreach (array_chunk(array_values(array_unique($wishlistIds)), self::BATCH_SIZE) as $batch) {
            $criteria = (new Criteria())->addFilter(new EqualsAnyFilter('wishlistId', $batch));
            foreach ($this->wishlistProductRepository->search($criteria, $context) as $relation) {
                $relations[$relation->getWishlistId()."\0".$relation->getProductId()] = true;
            }
        }

        return $relations;
    }

    /**
     * @template TCollection of EntityCollection
     *
     * @param EntityRepository<TCollection> $repository
     * @param list<array<string, string>>   $payloads
     *
     * @return array{int, int, list<class-string<\Throwable>>, list<string>}
     */
    private function write(EntityRepository $repository, array $payloads, Context $context): array
    {
        $written = 0;
        $failed = 0;
        $exceptionClasses = [];
        $writtenIds = [];
        foreach (array_chunk($payloads, self::BATCH_SIZE) as $batch) {
            [$batchWritten, $batchFailed, $batchClasses, $batchIds] = $this->writeBatch($repository, $batch, $context);
            $written += $batchWritten;
            $failed += $batchFailed;
            $exceptionClasses = [...$exceptionClasses, ...$batchClasses];
            $writtenIds = [...$writtenIds, ...$batchIds];
        }

        return [$written, $failed, $exceptionClasses, $writtenIds];
    }

    /**
     * @template TCollection of EntityCollection
     *
     * @param EntityRepository<TCollection>         $repository
     * @param non-empty-list<array<string, string>> $payloads
     *
     * @return array{int, int, list<class-string<\Throwable>>, list<string>}
     */
    private function writeBatch(EntityRepository $repository, array $payloads, Context $context): array
    {
        try {
            $repository->upsert($payloads, $context);

            return [count($payloads), 0, [], array_column($payloads, 'id')];
        } catch (WriteException $exception) {
            if (1 === count($payloads)) {
                return [0, 1, [$exception::class], []];
            }
        }

        $middle = intdiv(count($payloads), 2);
        [$leftWritten, $leftFailed, $leftClasses, $leftIds] = $this->writeBatch($repository, array_slice($payloads, 0, $middle), $context);
        [$rightWritten, $rightFailed, $rightClasses, $rightIds] = $this->writeBatch($repository, array_slice($payloads, $middle), $context);

        return [$leftWritten + $rightWritten, $leftFailed + $rightFailed, [...$leftClasses, ...$rightClasses], [...$leftIds, ...$rightIds]];
    }
}
