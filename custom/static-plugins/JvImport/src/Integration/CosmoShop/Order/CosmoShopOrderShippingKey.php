<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Order;

enum CosmoShopOrderShippingKey: string
{
    case FREIGHT_FORWARDER = 'freight_forwarder';
    case FREIGHT_FORWARDER_INSTALLATION = 'freight_forwarder_to_installation_location';
    case SELF_PICKUP = 'self_pickup';
}
