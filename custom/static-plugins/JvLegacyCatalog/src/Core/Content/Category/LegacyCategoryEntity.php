<?php declare(strict_types=1);

namespace Jv\LegacyCatalog\Core\Content\Category;

use Jv\LegacyCatalog\Core\Content\CategoryContent\LegacyCategoryContentCollection;
use Jv\LegacyCatalog\Core\Content\Source\LegacyCatalogSourceEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class LegacyCategoryEntity extends Entity
{
    use EntityIdTrait;

    protected string $sourceId;
    protected int $sourceCategoryId;
    protected int $sourceParentId;
    protected ?string $parentId = null;
    protected ?int $rubricOrder = null;
    protected string $displayName;
    protected ?string $sourceCategoryNumber = null;
    protected ?string $urlKey = null;
    /** @var array<string, mixed> */
    protected array $rubricData = [];
    protected ?LegacyCatalogSourceEntity $source = null;
    protected ?self $parent = null;
    protected ?LegacyCategoryCollection $children = null;
    protected ?LegacyCategoryContentCollection $contents = null;

    public function getSourceId(): string
    {
        return $this->sourceId;
    }

    public function getSourceCategoryId(): int
    {
        return $this->sourceCategoryId;
    }

    public function getSourceParentId(): int
    {
        return $this->sourceParentId;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function getRubricOrder(): ?int
    {
        return $this->rubricOrder;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getSourceCategoryNumber(): ?string
    {
        return $this->sourceCategoryNumber;
    }

    public function getUrlKey(): ?string
    {
        return $this->urlKey;
    }

    /** @return array<string, mixed> */
    public function getRubricData(): array
    {
        return $this->rubricData;
    }

    public function getSource(): ?LegacyCatalogSourceEntity
    {
        return $this->source;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function getChildren(): ?LegacyCategoryCollection
    {
        return $this->children;
    }

    public function getContents(): ?LegacyCategoryContentCollection
    {
        return $this->contents;
    }
}
