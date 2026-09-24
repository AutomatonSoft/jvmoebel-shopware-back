<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\AfterCool;

use Jv\Import\Service\AfterCool\Parser\AfterCoolSourceFileParser;
use PHPUnit\Framework\TestCase;

final class AfterCoolSourceFileParserTest extends TestCase
{
    private AfterCoolSourceFileParser $parser;

    protected function setUp(): void
    {
        $this->parser = new AfterCoolSourceFileParser();
    }

    public function testItParsesFormatAWithRegion(): void
    {
        $metadata = $this->parser->parse('GANASI_SOFA__CN__477277.csv');

        self::assertNotNull($metadata);
        self::assertSame('GANASI_SOFA', $metadata->prefix);
        self::assertSame('CN', $metadata->region);
        self::assertSame(477277, $metadata->factoryId);
    }

    public function testItParsesFormatBWithoutRegion(): void
    {
        $metadata = $this->parser->parse('UK-GANASI_498371.csv');

        self::assertNotNull($metadata);
        self::assertSame('UK-GANASI', $metadata->prefix);
        self::assertNull($metadata->region);
        self::assertSame(498371, $metadata->factoryId);
    }

    public function testItParsesUkSkorpionFormatB(): void
    {
        $metadata = $this->parser->parse('UK-Skorpion_498681.csv');

        self::assertNotNull($metadata);
        self::assertSame('UK-Skorpion', $metadata->prefix);
        self::assertSame(498681, $metadata->factoryId);
    }

    public function testItParsesFormatCWithoutRegionSegment(): void
    {
        $metadata = $this->parser->parse('Others_SOFORT_LIEFERBAR_DELETE_DONT_TOUCH__393532.csv');

        self::assertNotNull($metadata);
        self::assertSame('Others_SOFORT_LIEFERBAR_DELETE_DONT_TOUCH', $metadata->prefix);
        self::assertNull($metadata->region);
        self::assertSame(393532, $metadata->factoryId);
    }

    public function testItPrefersFormatAOverFormatCWhenRegionPresent(): void
    {
        $metadata = $this->parser->parse('GANASI_SOFA__CN__477277.csv');

        self::assertNotNull($metadata);
        self::assertSame('GANASI_SOFA', $metadata->prefix);
        self::assertSame('CN', $metadata->region);
        self::assertSame(477277, $metadata->factoryId);
    }

    public function testItParsesRichPrefixFormatB(): void
    {
        $metadata = $this->parser->parse('EPOXID__CN__LOUVRE_FOSHAN_503713.csv');

        self::assertNotNull($metadata);
        self::assertSame('EPOXID__CN__LOUVRE_FOSHAN', $metadata->prefix);
        self::assertSame(503713, $metadata->factoryId);
    }

    public function testItReturnsNullForUnknownFormat(): void
    {
        self::assertNull($this->parser->parse('invalid.csv'));
        self::assertNull($this->parser->parse(null));
        self::assertNull($this->parser->parse(''));
    }
}
