<?php declare(strict_types=1);

namespace Jv\Promotion\Core\Content\JvPromotionTarget;

use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class JvPromotionTargetEntity extends Entity
{
    use EntityIdTrait;

    protected string $promotionId;
    protected string $targetType;
    protected ?int $factoryId = null;
    protected ?string $stammartikelId = null;
    protected ?string $sourceFilePrefix = null;
    protected ?string $productId = null;
    protected ?string $productVersionId = null;
    protected ?string $ean = null;
    protected float $discountPercent;
    protected ?PromotionEntity $promotion = null;

    public function getPromotionId(): string
    {
        return $this->promotionId;
    }

    public function getTargetType(): string
    {
        return $this->targetType;
    }

    public function getFactoryId(): ?int
    {
        return $this->factoryId;
    }

    public function getStammartikelId(): ?string
    {
        return $this->stammartikelId;
    }

    public function getSourceFilePrefix(): ?string
    {
        return $this->sourceFilePrefix;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function getProductVersionId(): ?string
    {
        return $this->productVersionId;
    }

    public function getEan(): ?string
    {
        return $this->ean;
    }

    public function getDiscountPercent(): float
    {
        return $this->discountPercent;
    }

    public function getPromotion(): ?PromotionEntity
    {
        return $this->promotion;
    }
}
