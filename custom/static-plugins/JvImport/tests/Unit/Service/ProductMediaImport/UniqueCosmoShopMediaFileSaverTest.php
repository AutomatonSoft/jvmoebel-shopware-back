<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\ProductMediaImport;

use Jv\Import\Service\ProductMediaImport\UniqueCosmoShopMediaFileSaver;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;

final class UniqueCosmoShopMediaFileSaverTest extends TestCase
{
    public function testItRetriesOnlyCosmoShopImportFilenameCollisionsWithUniqueDestination(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $context->addExtension(UniqueCosmoShopMediaFileSaver::CONTEXT_EXTENSION, new ArrayStruct());
        $mediaFile = $this->createMock(MediaFile::class);
        $inner = $this->createMock(FileSaver::class);
        $attempt = 0;

        $inner->expects(self::exactly(2))
            ->method('persistFileToMedia')
            ->willReturnCallback(function (MediaFile $actualFile, string $destination, string $actualMediaId, Context $actualContext) use (&$attempt, $mediaFile, $mediaId, $context): void {
                self::assertSame($mediaFile, $actualFile);
                self::assertSame($mediaId, $actualMediaId);
                self::assertSame($context, $actualContext);

                if (0 === $attempt++) {
                    self::assertSame('1', $destination);

                    throw MediaException::duplicatedMediaFileName('1', 'jpg');
                }

                self::assertSame('1--'.substr($mediaId, 0, 12), $destination);
            });

        (new UniqueCosmoShopMediaFileSaver($inner))->persistFileToMedia($mediaFile, '1', $mediaId, $context);
    }

    public function testItDoesNotChangeNonCosmoShopMediaWrites(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $mediaFile = $this->createMock(MediaFile::class);
        $inner = $this->createMock(FileSaver::class);
        $exception = MediaException::duplicatedMediaFileName('1', 'jpg');

        $inner->expects(self::once())
            ->method('persistFileToMedia')
            ->with($mediaFile, '1', $mediaId, $context)
            ->willThrowException($exception);

        $this->expectExceptionObject($exception);

        (new UniqueCosmoShopMediaFileSaver($inner))->persistFileToMedia($mediaFile, '1', $mediaId, $context);
    }

    public function testItRethrowsOtherMediaFailuresDuringACosmoShopImport(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $context->addExtension(UniqueCosmoShopMediaFileSaver::CONTEXT_EXTENSION, new ArrayStruct());
        $mediaFile = $this->createMock(MediaFile::class);
        $inner = $this->createMock(FileSaver::class);
        $exception = MediaException::emptyMediaFilename();

        $inner->expects(self::once())
            ->method('persistFileToMedia')
            ->with($mediaFile, '1', $mediaId, $context)
            ->willThrowException($exception);

        $this->expectExceptionObject($exception);

        (new UniqueCosmoShopMediaFileSaver($inner))->persistFileToMedia($mediaFile, '1', $mediaId, $context);
    }

    public function testItOverridesEveryPublicMethodOfTheDecoratedFileSaver(): void
    {
        $decorator = new \ReflectionClass(UniqueCosmoShopMediaFileSaver::class);

        foreach ((new \ReflectionClass(FileSaver::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor()) {
                continue;
            }

            self::assertSame(
                UniqueCosmoShopMediaFileSaver::class,
                $decorator->getMethod($method->getName())->getDeclaringClass()->getName(),
                sprintf('%s() is inherited from FileSaver and would run on an uninitialised parent.', $method->getName()),
            );
        }
    }
}
