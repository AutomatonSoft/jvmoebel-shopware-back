<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport\Dto;

enum OrderLineItemKind: string
{
    case PRODUCT = 'product';
    case SHIPPING = 'shipping';
    case PAYMENT_ADJUSTMENT = 'payment_adjustment';
}
