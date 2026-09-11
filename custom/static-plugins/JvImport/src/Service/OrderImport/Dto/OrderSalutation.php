<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport\Dto;

/** Source salutation is closed by SPEC-021 to m/f/w/d and the empty value; anything else is invalid. */
enum OrderSalutation: string
{
    case M = 'm';
    case F = 'f';
    case W = 'w';
    case D = 'd';
    case NONE = '';
}
