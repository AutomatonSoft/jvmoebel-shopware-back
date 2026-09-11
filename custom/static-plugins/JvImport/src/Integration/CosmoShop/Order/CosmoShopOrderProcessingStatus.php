<?php declare(strict_types=1);

namespace Jv\Import\Integration\CosmoShop\Order;

enum CosmoShopOrderProcessingStatus: string
{
    case IN_PROGRESS = '1';
    case COMPLETED = '5';
    case OPEN = '8';
}
