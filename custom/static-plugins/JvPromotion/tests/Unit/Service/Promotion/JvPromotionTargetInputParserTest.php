<?php declare(strict_types=1);

namespace Jv\Promotion\Tests\Unit\Service\Promotion;

use Jv\Promotion\Core\Content\JvPromotionTarget\JvPromotionTargetDefinition;
use Jv\Promotion\Service\Promotion\JvPromotionTargetInputParser;
use PHPUnit\Framework\TestCase;

final class JvPromotionTargetInputParserTest extends TestCase
{
    private JvPromotionTargetInputParser $parser;

    protected function setUp(): void
    {
        $this->parser = new JvPromotionTargetInputParser();
    }

    public function testItParsesValidTargetsAndCollectsWarnings(): void
    {
        $result = $this->parser->parse([
            ['type' => 'factory', 'factoryId' => 498371],
            ['type' => 'collection', 'factoryId' => 498371, 'stammartikelId' => '175220799'],
            ['type' => 'unknown'],
            ['type' => 'factory', 'factoryId' => 0],
        ]);

        self::assertCount(2, $result['targets']);
        self::assertSame(JvPromotionTargetDefinition::TARGET_FACTORY, $result['targets'][0]->type);
        self::assertSame(JvPromotionTargetDefinition::TARGET_COLLECTION, $result['targets'][1]->type);
        self::assertNotEmpty($result['warnings']);
    }
}
