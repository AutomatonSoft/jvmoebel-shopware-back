<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport\Dto;

enum OrderPriceDisplay: string
{
    case BRUTTO = 'brutto';
    case NETTO = 'netto';
}
