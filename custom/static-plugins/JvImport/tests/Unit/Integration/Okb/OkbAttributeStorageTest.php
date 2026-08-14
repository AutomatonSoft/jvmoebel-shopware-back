<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\Okb;

use Jv\Import\Integration\Okb\OkbAttributeStorage;
use PHPUnit\Framework\TestCase;

final class OkbAttributeStorageTest extends TestCase
{
    public function testItUsesPropertiesForVariationAndNavigationAttributes(): void
    {
        self::assertSame(OkbAttributeStorage::Property, OkbAttributeStorage::fromFeatureRelevance('VARIATION_THEME|PRODUCT_DETAILS'));
        self::assertSame(OkbAttributeStorage::Property, OkbAttributeStorage::fromFeatureRelevance('PRODUCT_DETAILS|FILTER|NAVIGATION|SEARCH'));
    }

    public function testItUsesCustomFieldsForProductDetailsOnly(): void
    {
        self::assertSame(OkbAttributeStorage::CustomField, OkbAttributeStorage::fromFeatureRelevance('TITLE|PRODUCT_DETAILS'));
    }
}
