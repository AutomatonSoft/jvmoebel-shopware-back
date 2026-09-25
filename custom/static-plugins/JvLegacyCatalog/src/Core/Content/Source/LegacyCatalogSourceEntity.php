<?php declare(strict_types=1);

namespace Jv\LegacyCatalog\Core\Content\Source;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class LegacyCatalogSourceEntity extends Entity
{
    use EntityIdTrait;

    protected string $sourceSystem;
    protected string $sourceProject;
    protected string $salesChannelId;
    protected int $formatVersion;
    protected string $categoriesSha256;
    protected int $categoryCount;
    protected int $contentCount;
    protected int $seoCount;
    protected \DateTimeInterface $importedAt;
    protected ?SalesChannelEntity $salesChannel = null;

    public function getSourceSystem(): string
    {
        return $this->sourceSystem;
    }

    public function getSourceProject(): string
    {
        return $this->sourceProject;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getFormatVersion(): int
    {
        return $this->formatVersion;
    }

    public function getCategoriesSha256(): string
    {
        return $this->categoriesSha256;
    }

    public function getCategoryCount(): int
    {
        return $this->categoryCount;
    }

    public function getContentCount(): int
    {
        return $this->contentCount;
    }

    public function getSeoCount(): int
    {
        return $this->seoCount;
    }

    public function getImportedAt(): \DateTimeInterface
    {
        return $this->importedAt;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }
}
