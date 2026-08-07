<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

enum ButtonVariant: string
{
    case Primary = 'primary';
    case Secondary = 'secondary';
    case Link = 'link';

    public static function fromConfig(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Primary;
    }
}
