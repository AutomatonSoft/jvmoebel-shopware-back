<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\CosmoShop\Media;

use Jv\Import\Integration\CosmoShop\Media\CosmoShopLegacyProductImageUrlExpander;
use PHPUnit\Framework\TestCase;

final class CosmoShopLegacyProductImageUrlExpanderTest extends TestCase
{
    public function testItExpandsMainImageResizesWithoutChangingTheLegacyPrefix(): void
    {
        $urls = (new CosmoShopLegacyProductImageUrlExpander())->expand(
            'https://www.jvmoebel.de/cosmoshop/default/pix/a/n/1742011895-159847-1.3.jpg',
        );

        self::assertNotNull($urls);
        self::assertSame('main:1742011895-159847.3.jpg', $urls->targetKey);
        self::assertSame([
            'https://www.jvmoebel.de/cosmoshop/default/pix/a/v/1742011895-159847-0.3.jpg',
            'https://www.jvmoebel.de/cosmoshop/default/pix/a/n/1742011895-159847-1.3.jpg',
            'https://www.jvmoebel.de/cosmoshop/default/pix/a/g/1742011895-159847-2.3.jpg',
        ], $urls->urls);
    }

    public function testItExpandsAllKnownGalleryAliasesAndRetainsTheQueryString(): void
    {
        $urls = (new CosmoShopLegacyProductImageUrlExpander())->expand(
            'https://www.jvmoebel.de/cosmoshop/default/pix/a/z/SKU-1/gallery.2.jpg?cache=1',
        );

        self::assertNotNull($urls);
        self::assertSame('gallery:SKU-1:gallery.2.jpg', $urls->targetKey);
        self::assertSame([
            'https://www.jvmoebel.de/cosmoshop/default/pix/a/z/SKU-1/gallery.2.jpg?cache=1',
            'https://www.jvmoebel.de/cosmoshop/default/pix/a/z/SKU-1/g/gallery.2.jpg?cache=1',
            'https://www.jvmoebel.de/cosmoshop/default/pix/a/zg/SKU-1/gallery.2.jpg?cache=1',
        ], $urls->urls);
    }

    public function testItRecognisesTheHistoricalGalleryLargeAlias(): void
    {
        $urls = (new CosmoShopLegacyProductImageUrlExpander())->expand(
            'https://www.jvmoebel.de/cosmoshop/default/pix/a/zg/SKU-1/gallery.2.jpg',
        );

        self::assertNotNull($urls);
        self::assertSame('gallery:SKU-1:gallery.2.jpg', $urls->targetKey);
        self::assertSame([
            'https://www.jvmoebel.de/cosmoshop/default/pix/a/z/SKU-1/gallery.2.jpg',
            'https://www.jvmoebel.de/cosmoshop/default/pix/a/z/SKU-1/g/gallery.2.jpg',
            'https://www.jvmoebel.de/cosmoshop/default/pix/a/zg/SKU-1/gallery.2.jpg',
        ], $urls->urls);
    }

    public function testItIgnoresUnrecognisedMediaUrls(): void
    {
        self::assertNull((new CosmoShopLegacyProductImageUrlExpander())->expand('https://www.jvmoebel.de/media/old-sofa.jpg'));
    }
}
