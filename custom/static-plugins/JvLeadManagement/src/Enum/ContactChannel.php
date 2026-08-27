<?php declare(strict_types=1);

namespace Jv\LeadManagement\Enum;

enum ContactChannel: string
{
    case Form = 'form';
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case Phone = 'phone';
}
