<?php declare(strict_types=1);

namespace Jv\LegacyCatalog\Core\Content\CategoryContent;

use Jv\LegacyCatalog\Core\Content\Category\LegacyCategoryDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class LegacyCategoryContentDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'jv_legacy_category_content';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return LegacyCategoryContentEntity::class;
    }

    public function getCollectionClass(): string
    {
        return LegacyCategoryContentCollection::class;
    }

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([new WriteProtection(Context::SYSTEM_SCOPE)]);
    }

    protected function defineFields(): FieldCollection
    {
        $api = new ApiAware(AdminApiSource::class);

        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags($api, new Required(), new PrimaryKey()),
            (new FkField('category_id', 'categoryId', LegacyCategoryDefinition::class))->addFlags($api, new Required()),
            (new StringField('source_language', 'sourceLanguage', 32))->addFlags($api, new Required()),
            (new LongTextField('rubnam', 'rubnam'))->addFlags(new AllowHtml(false), new AllowEmptyString()),
            (new LongTextField('rubtext', 'rubtext'))->addFlags(new AllowHtml(false), new AllowEmptyString()),
            (new LongTextField('rubtext_kurz', 'rubtextKurz'))->addFlags(new AllowHtml(false), new AllowEmptyString()),
            (new LongTextField('urlkey', 'urlkey'))->addFlags(new AllowHtml(false), new AllowEmptyString()),
            (new LongTextField('page_title', 'pageTitle'))->addFlags(new AllowHtml(false), new AllowEmptyString()),
            (new LongTextField('meta_description', 'metaDescription'))->addFlags(new AllowHtml(false), new AllowEmptyString()),
            (new LongTextField('meta_keywords', 'metaKeywords'))->addFlags(new AllowHtml(false), new AllowEmptyString()),
            (new JsonField('raw_data', 'rawData'))->addFlags($api, new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new ManyToOneAssociationField('category', 'category_id', LegacyCategoryDefinition::class, 'id', false))->addFlags($api),
        ]);
    }
}
