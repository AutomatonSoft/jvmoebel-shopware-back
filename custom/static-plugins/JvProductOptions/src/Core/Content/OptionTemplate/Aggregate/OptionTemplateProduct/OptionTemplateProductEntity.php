<?php declare(strict_types=1);

namespace Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateProduct;

use Jv\ProductOptions\Core\Content\OptionTemplate\OptionTemplateEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

final class OptionTemplateProductEntity extends Entity
{
    protected string $productId;

    protected string $productVersionId;

    protected string $templateId;

    protected ?ProductEntity $product = null;

    protected ?OptionTemplateEntity $template = null;

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getProductVersionId(): string
    {
        return $this->productVersionId;
    }

    public function setProductVersionId(string $productVersionId): void
    {
        $this->productVersionId = $productVersionId;
    }

    public function getTemplateId(): string
    {
        return $this->templateId;
    }

    public function setTemplateId(string $templateId): void
    {
        $this->templateId = $templateId;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getTemplate(): ?OptionTemplateEntity
    {
        return $this->template;
    }

    public function setTemplate(?OptionTemplateEntity $template): void
    {
        $this->template = $template;
    }
}
