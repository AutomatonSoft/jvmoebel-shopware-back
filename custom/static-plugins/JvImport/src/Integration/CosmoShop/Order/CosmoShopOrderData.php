<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Order;

/** Immutable, validated source aggregate. It deliberately has no raw-record escape hatch. */
final readonly class CosmoShopOrderData
{
    /** @param list<CosmoShopOrderAddress> $packingAddresses
     * @param list<CosmoShopOrderLineItem> $lineItems
     * @param list<CosmoShopOrderHistoryEntry> $history
     */
    public function __construct(public int $sourceOrderId, public ?int $sourceCustomerId, public string $orderNumber, public ?string $createdAt, public string $submittedAt, public ?string $paidAt, public string $totalNet, public string $totalTax, public string $customerComment, public string $priceDisplay, public CosmoShopOrderVatType $vatType, public CosmoShopOrderProcessingStatus $processingStatus, public CosmoShopOrderAddress $billingAddress, public ?CosmoShopOrderAddress $shippingAddress, public array $packingAddresses, public CosmoShopOrderPayment $payment, public CosmoShopOrderShipping $shipping, public array $lineItems, public array $history, public ?string $mailArtifactRef, private string $canonicalJson)
    {
    }

    public function checksum(string $mappingVersion): string
    {
        return hash('sha256', $mappingVersion."\0".$this->canonicalJson);
    }
}

final readonly class CosmoShopOrderAddress
{
    public function __construct(public string $sourceType, public ?int $sourceAddressId, public string $salutation, public string $title, public string $firstName, public string $lastName, public string $company, public string $street, public string $zipcode, public string $city, public string $country, public string $state, public string $email, public string $phone, public string $vatId) {}

    public function isComplete(bool $requiresEmail = false): bool
    {
        foreach ([$this->firstName, $this->lastName, $this->street, $this->zipcode, $this->city, $this->country] as $value) {
            if ('' === trim($value)) return false;
        }

        return !$requiresEmail || '' !== trim($this->email);
    }
}

final readonly class CosmoShopOrderPayment
{
    public function __construct(public CosmoShopOrderPaymentKey $key, public string $label, public string $sourcePlugin, public ?string $transactionReference) {}
}

final readonly class CosmoShopOrderShipping
{
    public function __construct(public CosmoShopOrderShippingKey $key, public string $label, public ?int $sourceCarrierId) {}
}

final readonly class CosmoShopOrderLineItem
{
    /** @param array<string, mixed> $snapshot Opaque validated snapshot, consumed only by payload projection. */
    public function __construct(public int $sourcePositionId, public int $position, public string $kind, public string $mainProductNumber, public string $productNumber, public string $label, public string $description, public int $quantity, public string $taxRate, public string $unitNet, public string $unitTax, public string $totalNet, public string $totalTax, public array $snapshot) {}
}

final readonly class CosmoShopOrderHistoryEntry
{
    public function __construct(public string $occurredAt, public CosmoShopOrderHistoryStatus $status) {}
}
