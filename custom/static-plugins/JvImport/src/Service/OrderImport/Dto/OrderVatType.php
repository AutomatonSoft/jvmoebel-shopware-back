<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport\Dto;

enum OrderVatType: string
{
    case NORMAL = 'normal';
    case EU_EXEMPT = 'ustid-befreit';
    case NON_EU = 'non-eu';
}
