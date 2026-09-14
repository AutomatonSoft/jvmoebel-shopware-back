<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport;

use Jv\Import\Integration\CosmoShop\Order\CosmoShopOrderIdentity;
use Jv\Import\Service\OrderImport\Exception\CosmoShopOrderConfigurationException;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final readonly class CosmoShopLegacyMethodBootstrapper
{
    /** @param EntityRepository<PaymentMethodCollection> $paymentMethods
     * @param EntityRepository<ShippingMethodCollection> $shippingMethods
     */
    public function __construct(private EntityRepository $paymentMethods, private EntityRepository $shippingMethods)
    {
    }

    public function prepare(Market $market, SalesChannelEntity $salesChannel, Context $context): void
    {
        $defaultShipping = $this->shippingMethods->search(new Criteria([$salesChannel->getShippingMethodId()]), $context)->first();
        if (!$defaultShipping instanceof ShippingMethodEntity) {
            throw new CosmoShopOrderConfigurationException('Market shipping method is unavailable.');
        }
        $paymentLabels = [
            'amazon_pay' => 'Amazon Pay', 'cash_on_delivery' => 'Cash on delivery', 'easycredit' => 'easyCredit',
            'installment_purchase' => 'Installment purchase', 'invoice' => 'Invoice', 'klarna' => 'Klarna',
            'klarna_pay_later' => 'Klarna Pay Later', 'klarna_pay_now' => 'Klarna Pay Now', 'klarna_payments' => 'Klarna Payments',
            'paypal' => 'PayPal', 'paypal_express' => 'PayPal Express', 'prepayment_discount' => 'Prepayment discount',
            'santander_financing' => 'Santander financing', 'skrill' => 'Skrill', 'split_deposit' => 'Split deposit',
        ];
        $shippingLabels = [
            'freight_forwarder' => 'Freight forwarding',
            'freight_forwarder_to_installation_location' => 'Freight forwarding to installation location',
            'self_pickup' => 'Self pickup',
        ];
        $this->paymentMethods->upsert(array_map(static fn (string $key, string $label): array => [
            'id' => CosmoShopOrderIdentity::paymentMethodId($market, $key),
            'technicalName' => 'jv_cosmoshop_'.$market->domain().'_payment_'.$key,
            'name' => $label,
            'active' => false,
        ], array_keys($paymentLabels), $paymentLabels), $context);
        $this->shippingMethods->upsert(array_map(static fn (string $key, string $label): array => [
            'id' => CosmoShopOrderIdentity::shippingMethodId($market, $key),
            'technicalName' => 'jv_cosmoshop_'.$market->domain().'_shipping_'.$key,
            'name' => $label,
            'active' => false,
            'deliveryTimeId' => $defaultShipping->getDeliveryTimeId(),
        ], array_keys($shippingLabels), $shippingLabels), $context);
    }
}
