<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport\Dto;

/** Immutable, validated source order aggregate. It deliberately has no raw-record escape hatch. */
final readonly class OrderImportData
{
    /**
     * @param list<OrderAddressData>      $packingAddresses
     * @param list<OrderLineItemData>     $lineItems
     * @param list<OrderHistoryEntryData> $history
     */
    public function __construct(
        public int $sourceOrderId,
        public ?int $sourceCustomerId,
        public string $orderNumber,
        public ?string $createdAt,
        public string $submittedAt,
        public ?string $paidAt,
        public string $currency,
        public string $language,
        public OrderPriceDisplay $priceDisplay,
        public OrderVatType $vatType,
        public OrderProcessingStatus $processingStatus,
        public string $totalNet,
        public string $totalTax,
        public string $customerComment,
        public OrderAddressData $billingAddress,
        public ?OrderAddressData $shippingAddress,
        public array $packingAddresses,
        public OrderPaymentData $payment,
        public OrderShippingData $shipping,
        public array $lineItems,
        public array $history,
        public ?string $mailArtifactRef,
        private string $canonicalJson,
    ) {
    }

    /** Deterministic per source snapshot and projection version, so a projection change safely reprocesses it. */
    public function checksum(string $mappingVersion): string
    {
        return hash('sha256', $mappingVersion."\0".$this->canonicalJson);
    }
}
