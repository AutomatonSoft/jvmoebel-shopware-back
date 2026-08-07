<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ButtonVariant;
use PHPUnit\Framework\TestCase;

final class ButtonVariantTest extends TestCase
{
    public function testItMapsKnownConfigValues(): void
    {
        self::assertSame(ButtonVariant::Primary, ButtonVariant::fromConfig('primary'));
        self::assertSame(ButtonVariant::Secondary, ButtonVariant::fromConfig('secondary'));
        self::assertSame(ButtonVariant::Link, ButtonVariant::fromConfig('link'));
    }

    public function testItFallsBackToPrimaryForUnknownOrEmptyValues(): void
    {
        self::assertSame(ButtonVariant::Primary, ButtonVariant::fromConfig(null));
        self::assertSame(ButtonVariant::Primary, ButtonVariant::fromConfig(''));
        self::assertSame(ButtonVariant::Primary, ButtonVariant::fromConfig('huge'));
    }
}
