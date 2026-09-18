<?php declare(strict_types=1);

namespace Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValue;

use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateGroup\OptionTemplateGroupEntity;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValueTranslation\OptionTemplateValueTranslationCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;

final class OptionTemplateValueEntity extends Entity
{
    use EntityIdTrait;

    protected string $groupId;

    protected int $position = 0;

    protected ?string $mediaId = null;

    protected ?string $colorHex = null;

    protected string $surchargeType = 'fixed';

    protected ?PriceCollection $surchargePrice = null;

    protected ?float $surchargePercentage = null;

    protected ?string $name = null;

    protected ?OptionTemplateGroupEntity $group = null;

    protected ?MediaEntity $media = null;

    protected ?OptionTemplateValueTranslationCollection $translations = null;

    public function getGroupId(): string
    {
        return $this->groupId;
    }

    public function setGroupId(string $groupId): void
    {
        $this->groupId = $groupId;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function setMediaId(?string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getColorHex(): ?string
    {
        return $this->colorHex;
    }

    public function setColorHex(?string $colorHex): void
    {
        $this->colorHex = $colorHex;
    }

    public function getSurchargeType(): string
    {
        return $this->surchargeType;
    }

    public function setSurchargeType(string $surchargeType): void
    {
        $this->surchargeType = $surchargeType;
    }

    public function getSurchargePrice(): ?PriceCollection
    {
        return $this->surchargePrice;
    }

    public function setSurchargePrice(?PriceCollection $surchargePrice): void
    {
        $this->surchargePrice = $surchargePrice;
    }

    public function getSurchargePercentage(): ?float
    {
        return $this->surchargePercentage;
    }

    public function setSurchargePercentage(?float $surchargePercentage): void
    {
        $this->surchargePercentage = $surchargePercentage;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getGroup(): ?OptionTemplateGroupEntity
    {
        return $this->group;
    }

    public function setGroup(?OptionTemplateGroupEntity $group): void
    {
        $this->group = $group;
    }

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(?MediaEntity $media): void
    {
        $this->media = $media;
    }

    public function getTranslations(): ?OptionTemplateValueTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(OptionTemplateValueTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
