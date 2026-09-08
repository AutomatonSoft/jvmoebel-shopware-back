<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\AfterCool;

use Jv\Import\Service\AfterCool\Media\LinkAfterCoolExternalMediaService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Upload\MediaUploadParameters;
use Shopware\Core\Content\Media\Upload\MediaUploadService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

final class LinkAfterCoolExternalMediaServiceTest extends TestCase
{
    public function testItCreatesDeterministicExternalLinksAndPreservesAnExistingCover(): void
    {
        $context = Context::createDefaultContext();
        $productId = Uuid::randomHex();
        $existingCoverId = Uuid::randomHex();
        $urls = [
            'https://images.example.test/cover.jpg',
            'https://images.example.test/side.png',
        ];
        $expectedMediaIds = array_map(
            static fn (string $url): string => Uuid::fromStringToHex('jvmoebel.aftercool.media.'.$url),
            $urls,
        );
        $mediaUpload = $this->getMockBuilder(MediaUploadService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['linkURL'])
            ->getMock();
        $call = 0;
        $mediaUpload->expects(self::exactly(2))->method('linkURL')->willReturnCallback(
            static function (string $url, Context $actualContext, MediaUploadParameters $parameters) use (&$call, $context, $urls, $expectedMediaIds): string {
                self::assertSame($urls[$call], $url);
                self::assertSame($context, $actualContext);
                self::assertSame($expectedMediaIds[$call], $parameters->id);
                self::assertSame(0 === $call ? 'image/jpeg' : 'image/png', $parameters->mimeType);
                self::assertTrue($parameters->deduplicate);

                return $expectedMediaIds[$call++];
            },
        );

        $result = (new LinkAfterCoolExternalMediaService($mediaUpload))->link(
            $productId,
            $urls,
            $existingCoverId,
            $context,
        );

        self::assertSame([], $result->issues);
        self::assertNull($result->coverId, 'A non-null result would overwrite the existing product cover.');
        self::assertSame([
            [
                'id' => Uuid::fromStringToHex('jvmoebel.aftercool.product-media.'.$productId.'.'.$expectedMediaIds[0]),
                'productId' => $productId,
                'mediaId' => $expectedMediaIds[0],
                'position' => 0,
            ],
            [
                'id' => Uuid::fromStringToHex('jvmoebel.aftercool.product-media.'.$productId.'.'.$expectedMediaIds[1]),
                'productId' => $productId,
                'mediaId' => $expectedMediaIds[1],
                'position' => 1,
            ],
        ], $result->productMedia);
    }

    public function testARejectedExternalUrlIsReportedWhileTheOtherImageAndProductContinue(): void
    {
        $context = Context::createDefaultContext();
        $productId = Uuid::randomHex();
        $validUrl = 'https://images.example.test/valid.jpg';
        $validMediaId = Uuid::fromStringToHex('jvmoebel.aftercool.media.'.$validUrl);
        $mediaUpload = $this->getMockBuilder(MediaUploadService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['linkURL'])
            ->getMock();
        $mediaUpload->expects(self::exactly(2))->method('linkURL')->willReturnCallback(
            static fn (string $url): string => str_contains($url, 'unavailable')
                ? throw new \RuntimeException('Private upstream response details.') : $validMediaId,
        );

        $result = (new LinkAfterCoolExternalMediaService($mediaUpload))->link(
            $productId,
            ['https://images.example.test/unavailable.jpg', $validUrl],
            null,
            $context,
        );

        self::assertCount(1, $result->productMedia);
        self::assertSame($validMediaId, $result->productMedia[0]['mediaId']);
        self::assertSame($result->productMedia[0]['id'], $result->coverId);
        self::assertCount(1, $result->issues);
        self::assertSame('external_media_link_failed', $result->issues[0]->code);
        self::assertStringNotContainsString('Private upstream response details.', $result->issues[0]->message);
    }
}
