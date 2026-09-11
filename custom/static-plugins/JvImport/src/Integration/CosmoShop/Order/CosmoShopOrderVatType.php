<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Order;

enum CosmoShopOrderVatType: string
{
    case NORMAL = 'normal';
    case EU_EXEMPT = 'ustid-befreit';
    case NON_EU = 'non-eu';
}
