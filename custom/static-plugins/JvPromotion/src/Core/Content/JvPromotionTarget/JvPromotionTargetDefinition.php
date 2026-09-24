<?php declare(strict_types=1);

namespace Jv\Promotion\Core\Content\JvPromotionTarget;

use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class JvPromotionTargetDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'jv_promotion_target';

    final public const TARGET_FACTORY = 'factory';
    final public const TARGET_COLLECTION = 'collection';
    final public const TARGET_FACTORY_PREFIX = 'factory_prefix';
    final public const TARGET_PRODUCT = 'product';
    final public const TARGET_EAN = 'ean';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return JvPromotionTargetEntity::class;
    }

    public function getCollectionClass(): string
    {
        return JvPromotionTargetCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('promotion_id', 'promotionId', PromotionDefinition::class))->addFlags(new Required()),
            (new StringField('target_type', 'targetType'))->addFlags(new Required()),
            new IntField('factory_id', 'factoryId'),
            new StringField('stammartikel_id', 'stammartikelId'),
            new StringField('source_file_prefix', 'sourceFilePrefix'),
            new FkField('product_id', 'productId', ProductDefinition::class),
            new ReferenceVersionField(ProductDefinition::class),
            new StringField('ean', 'ean'),
            (new FloatField('discount_percent', 'discountPercent'))->addFlags(new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('promotion', 'promotion_id', PromotionDefinition::class),
        ]);
    }
}
