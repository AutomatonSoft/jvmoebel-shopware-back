<?php declare(strict_types=1);

namespace Jv\Import\Core\Content\CatalogCategoryAttribute;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class CatalogCategoryAttributeEntity extends Entity
{
    use EntityIdTrait;

    protected string $sourceCode;
    protected string $categoryGroupId;
    protected ?string $categoryId = null;
    protected string $categoryVersionId;
    protected string $attributeId;
    protected string $attributeName;
    protected string $attributeType;
    protected ?string $featureRelevance = null;
    protected bool $multiValue;
    protected bool $active;
    protected bool $enabled;
    protected string $storage;
    protected ?string $propertyGroupId = null;
    protected ?string $customFieldName = null;
    protected ?PropertyGroupEntity $propertyGroup = null;
    protected ?CategoryEntity $category = null;

    public function getSourceCode(): string
    {
        return $this->sourceCode;
    }

    public function setSourceCode(string $sourceCode): void
    {
        $this->sourceCode = $sourceCode;
    }

    public function getCategoryGroupId(): string
    {
        return $this->categoryGroupId;
    }

    public function setCategoryGroupId(string $categoryGroupId): void
    {
        $this->categoryGroupId = $categoryGroupId;
    }

    public function getCategoryId(): ?string
    {
        return $this->categoryId;
    }

    public function setCategoryId(?string $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function getCategoryVersionId(): string
    {
        return $this->categoryVersionId;
    }

    public function setCategoryVersionId(string $categoryVersionId): void
    {
        $this->categoryVersionId = $categoryVersionId;
    }

    public function getAttributeId(): string
    {
        return $this->attributeId;
    }

    public function setAttributeId(string $attributeId): void
    {
        $this->attributeId = $attributeId;
    }

    public function getAttributeName(): string
    {
        return $this->attributeName;
    }

    public function setAttributeName(string $attributeName): void
    {
        $this->attributeName = $attributeName;
    }

    public function getAttributeType(): string
    {
        return $this->attributeType;
    }

    public function setAttributeType(string $attributeType): void
    {
        $this->attributeType = $attributeType;
    }

    public function getFeatureRelevance(): ?string
    {
        return $this->featureRelevance;
    }

    public function setFeatureRelevance(?string $featureRelevance): void
    {
        $this->featureRelevance = $featureRelevance;
    }

    public function isMultiValue(): bool
    {
        return $this->multiValue;
    }

    public function setMultiValue(bool $multiValue): void
    {
        $this->multiValue = $multiValue;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getStorage(): string
    {
        return $this->storage;
    }

    public function setStorage(string $storage): void
    {
        $this->storage = $storage;
    }

    public function getPropertyGroupId(): ?string
    {
        return $this->propertyGroupId;
    }

    public function setPropertyGroupId(?string $propertyGroupId): void
    {
        $this->propertyGroupId = $propertyGroupId;
    }

    public function getCustomFieldName(): ?string
    {
        return $this->customFieldName;
    }

    public function setCustomFieldName(?string $customFieldName): void
    {
        $this->customFieldName = $customFieldName;
    }

    public function getPropertyGroup(): ?PropertyGroupEntity
    {
        return $this->propertyGroup;
    }

    public function setPropertyGroup(?PropertyGroupEntity $propertyGroup): void
    {
        $this->propertyGroup = $propertyGroup;
    }

    public function getCategory(): ?CategoryEntity
    {
        return $this->category;
    }

    public function setCategory(?CategoryEntity $category): void
    {
        $this->category = $category;
    }
}
