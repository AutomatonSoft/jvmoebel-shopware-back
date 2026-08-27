<?php declare(strict_types=1);

namespace Jv\LeadManagement\Enum;

enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case OfferSent = 'offer_sent';
    case Won = 'won';
    case Lost = 'lost';
}