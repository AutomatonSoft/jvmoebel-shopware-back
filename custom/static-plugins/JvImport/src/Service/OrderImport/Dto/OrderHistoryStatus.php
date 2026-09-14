<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport\Dto;

enum OrderHistoryStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case SHIPPED = 'shipped';
    case CANCELLED = 'cancelled';
    case PAID = 'paid';
}
