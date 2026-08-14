<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\Okb;

use Jv\Import\Integration\Okb\OkbCatalogIdentity;
use PHPUnit\Framework\TestCase;

final class OkbCatalogIdentityTest extends TestCase
{
    public function testItCreatesStableSeparateIdsForCategoryLevels(): void
    {
        self::assertSame(OkbCatalogIdentity::categoryGroupId('3446'), OkbCatalogIdentity::categoryGroupId('3446'));
        self::assertSame(OkbCatalogIdentity::categoryId('25922'), OkbCatalogIdentity::categoryId('25922'));
        self::assertNotSame(OkbCatalogIdentity::categoryGroupId('3446'), OkbCatalogIdentity::categoryId('3446'));
    }

    public function testItSharesAPropertyGroupOnlyForTheSameSemanticAttribute(): void
    {
        self::assertSame(
            OkbCatalogIdentity::propertyGroupId('Farbe', 'STRING', false),
            OkbCatalogIdentity::propertyGroupId('Farbe', 'STRING', false),
        );
        self::assertNotSame(
            OkbCatalogIdentity::propertyGroupId('Farbe', 'STRING', false),
            OkbCatalogIdentity::propertyGroupId('Farbe', 'STRING', true),
        );
    }

    public function testItKeepsPdpAttributesSeparatedByOkbAttributeId(): void
    {
        self::assertSame('jv_okb_attribute_95652', OkbCatalogIdentity::customFieldName('95652'));
        self::assertNotSame(OkbCatalogIdentity::customFieldName('95652'), OkbCatalogIdentity::customFieldName('95653'));
    }
}
