<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\Catalog;

use Jv\Import\Service\Catalog\CatalogIdentity;
use PHPUnit\Framework\TestCase;

final class CatalogIdentityTest extends TestCase
{
    public function testItCreatesStableSeparateIdsForCategoryLevels(): void
    {
        self::assertSame(CatalogIdentity::categoryGroupId('okb', '3446'), CatalogIdentity::categoryGroupId('okb', '3446'));
        self::assertSame(CatalogIdentity::categoryId('okb', '25922'), CatalogIdentity::categoryId('okb', '25922'));
        self::assertNotSame(CatalogIdentity::categoryGroupId('okb', '3446'), CatalogIdentity::categoryId('okb', '3446'));
        self::assertNotSame(CatalogIdentity::categoryGroupId('okb', '3446'), CatalogIdentity::categoryGroupId('another-source', '3446'));
    }

    public function testItSharesOnePropertyGroupForOneAttributeNameRegardlessOfSourceType(): void
    {
        self::assertSame(
            CatalogIdentity::propertyGroupId('Farbe'),
            CatalogIdentity::propertyGroupId('Farbe'),
        );
        self::assertSame(
            CatalogIdentity::propertyGroupId('Breite'),
            CatalogIdentity::propertyGroupId('  breite  '),
        );
        self::assertNotSame(
            CatalogIdentity::propertyGroupId('Farbe'),
            CatalogIdentity::propertyGroupId('Material'),
        );
    }

    public function testItCreatesStablePropertyOptionIds(): void
    {
        self::assertSame(
            CatalogIdentity::propertyOptionId('property-group', 'Braun'),
            CatalogIdentity::propertyOptionId('property-group', 'Braun'),
        );
        self::assertNotSame(
            CatalogIdentity::propertyOptionId('property-group', 'Braun'),
            CatalogIdentity::propertyOptionId('property-group', 'Schwarz'),
        );
    }
}
