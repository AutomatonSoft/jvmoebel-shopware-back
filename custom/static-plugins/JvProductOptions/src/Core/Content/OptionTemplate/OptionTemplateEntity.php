<?php declare(strict_types=1);

namespace Jv\ProductOptions\Core\Content\OptionTemplate;

use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateGroup\OptionTemplateGroupCollection;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateProduct\OptionTemplateProductCollection;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateTranslation\OptionTemplateTranslationCollection;
use Shopware\Core\Content\ProductStream\ProductStreamCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class OptionTemplateEntity extends Entity
{
    use EntityIdTrait;

    protected bool $active = true;

    protected int $priority = 0;

    protected ?string $name = null;

    protected ?OptionTemplateGroupCollection $groups = null;

    protected ?ProductStreamCollection $productStreams = null;

    protected ?OptionTemplateProductCollection $products = null;

    protected ?OptionTemplateTranslationCollection $translations = null;

    public function getActive(): bool
    {
        return $this->active;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getGroups(): ?OptionTemplateGroupCollection
    {
        return $this->groups;
    }

    public function setGroups(?OptionTemplateGroupCollection $groups): void
    {
        $this->groups = $groups;
    }

    public function getProductStreams(): ?ProductStreamCollection
    {
        return $this->productStreams;
    }

    public function setProductStreams(?ProductStreamCollection $productStreams): void
    {
        $this->productStreams = $productStreams;
    }

    public function getProducts(): ?OptionTemplateProductCollection
    {
        return $this->products;
    }

    public function setProducts(?OptionTemplateProductCollection $products): void
    {
        $this->products = $products;
    }

    public function getTranslations(): ?OptionTemplateTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(?OptionTemplateTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
