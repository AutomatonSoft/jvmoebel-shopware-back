<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\ProductMediaImport;

use Jv\Import\Service\ProductMediaImport\PrepareCosmoShopProductMediaRecordService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

final class PrepareCosmoShopProductMediaRecordServiceTest extends TestCase
{
    public function testItSetsStableRelationsGalleryOrderAndCoverBeforeTheProductWrite(): void
    {
        $productId = Uuid::randomHex();
        $firstMediaId = Uuid::randomHex();
        $secondMediaId = Uuid::randomHex();

        $record = (new PrepareCosmoShopProductMediaRecordService())->execute([
            'id' => $productId,
            'media' => [
                ['media' => ['id' => $firstMediaId]],
                ['media' => ['id' => $secondMediaId]],
                ['media' => ['id' => $firstMediaId]],
            ],
        ], [
            'media' => 'https://images.example/first.jpg|https://images.example/second.jpg|https://images.example/first.jpg',
            'cover' => 'https://images.example/second.jpg',
        ]);

        self::assertSame([
            [
                'id' => Uuid::fromStringToHex('jvmoebel.product-media.'.$productId.$firstMediaId),
                'media' => ['id' => $firstMediaId, 'url' => 'https://images.example/first.jpg'],
                'position' => 0,
            ],
            [
                'id' => Uuid::fromStringToHex('jvmoebel.product-media.'.$productId.$secondMediaId),
                'media' => ['id' => $secondMediaId, 'url' => 'https://images.example/second.jpg'],
                'position' => 1,
            ],
        ], $record['media']);
        self::assertSame(Uuid::fromStringToHex('jvmoebel.product-media.'.$productId.$secondMediaId), $record['coverId']);
    }
}
