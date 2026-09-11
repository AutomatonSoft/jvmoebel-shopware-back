<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport\Dto;

/** Closed CosmoShop order processing status vocabulary; SPEC-021 mapping is exact. */
enum OrderProcessingStatus: string
{
    case IN_PROGRESS = '1';
    case COMPLETED = '5';
    case OPEN = '8';
}
