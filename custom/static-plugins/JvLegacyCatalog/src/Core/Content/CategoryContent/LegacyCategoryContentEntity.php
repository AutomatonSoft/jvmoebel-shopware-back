<?php declare(strict_types=1);

namespace Jv\LegacyCatalog\Core\Content\CategoryContent;

use Jv\LegacyCatalog\Core\Content\Category\LegacyCategoryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class LegacyCategoryContentEntity extends Entity
{
    use EntityIdTrait;

    protected string $categoryId;
    protected string $sourceLanguage;
    protected ?string $rubnam = null;
    protected ?string $rubtext = null;
    protected ?string $rubtextKurz = null;
    protected ?string $urlkey = null;
    protected ?string $pageTitle = null;
    protected ?string $metaDescription = null;
    protected ?string $metaKeywords = null;
    /** @var array<string, mixed> */
    protected array $rawData = [];
    protected ?LegacyCategoryEntity $category = null;

    public function getCategoryId(): string
    {
        return $this->categoryId;
    }

    public function getSourceLanguage(): string
    {
        return $this->sourceLanguage;
    }

    public function getRubnam(): ?string
    {
        return $this->rubnam;
    }

    public function getRubtext(): ?string
    {
        return $this->rubtext;
    }

    public function getRubtextKurz(): ?string
    {
        return $this->rubtextKurz;
    }

    public function getUrlkey(): ?string
    {
        return $this->urlkey;
    }

    public function getPageTitle(): ?string
    {
        return $this->pageTitle;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function getMetaKeywords(): ?string
    {
        return $this->metaKeywords;
    }

    /** @return array<string, mixed> */
    public function getRawData(): array
    {
        return $this->rawData;
    }

    public function getCategory(): ?LegacyCategoryEntity
    {
        return $this->category;
    }
}
