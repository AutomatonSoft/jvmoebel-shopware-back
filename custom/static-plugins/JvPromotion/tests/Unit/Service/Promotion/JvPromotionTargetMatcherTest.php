<?php declare(strict_types=1);

namespace Jv\Promotion\Tests\Unit\Service\Promotion;

use Jv\Promotion\Core\Content\JvPromotionTarget\JvPromotionTargetDefinition;
use Jv\Promotion\Service\AfterCool\JvAfterCoolProductMetadata;
use Jv\Promotion\Service\Promotion\JvPromotionTargetMatcher;
use PHPUnit\Framework\TestCase;

final class JvPromotionTargetMatcherTest extends TestCase
{
    private JvPromotionTargetMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new JvPromotionTargetMatcher();
    }

    public function testItMatchesFactoryTarget(): void
    {
        $metadata = new JvAfterCoolProductMetadata(498371, '175220799', 'UK-GANASI', '4260174422190');

        self::assertTrue($this->matcher->matches([
            'target_type' => JvPromotionTargetDefinition::TARGET_FACTORY,
            'factory_id' => 498371,
        ], 'product-id', '4260174422190', $metadata));
    }

    public function testItMatchesCollectionTarget(): void
    {
        $metadata = new JvAfterCoolProductMetadata(498371, '175220799', 'UK-GANASI', '4260174422190');

        self::assertTrue($this->matcher->matches([
            'target_type' => JvPromotionTargetDefinition::TARGET_COLLECTION,
            'factory_id' => 498371,
            'stammartikel_id' => '175220799',
        ], 'product-id', '4260174422190', $metadata));
    }

    public function testItPicksMaxPercentAcrossTargetsViaService(): void
    {
        $metadata = new JvAfterCoolProductMetadata(498371, '175220799', 'UK-GANASI', '4260174422190');

        self::assertFalse($this->matcher->matches([
            'target_type' => JvPromotionTargetDefinition::TARGET_COLLECTION,
            'factory_id' => 498371,
            'stammartikel_id' => '999',
        ], 'product-id', '4260174422190', $metadata));
    }

    public function testItMatchesProductByEanWithoutAfterCoolLink(): void
    {
        self::assertTrue($this->matcher->matches([
            'target_type' => JvPromotionTargetDefinition::TARGET_PRODUCT,
            'ean' => '4260174422190',
        ], 'product-id', '4260174422190', null));
    }
}
