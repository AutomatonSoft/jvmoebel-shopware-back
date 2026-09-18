<?php declare(strict_types=1);

namespace Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateGroup;

use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateGroupTranslation\OptionTemplateGroupTranslationCollection;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValue\OptionTemplateValueCollection;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValue\OptionTemplateValueEntity;
use Jv\ProductOptions\Core\Content\OptionTemplate\OptionTemplateEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class OptionTemplateGroupEntity extends Entity
{
    use EntityIdTrait;

    protected string $templateId;

    protected int $position = 0;

    protected ?string $defaultValueId = null;

    protected ?string $name = null;

    protected ?OptionTemplateEntity $template = null;

    protected ?OptionTemplateValueCollection $values = null;

    protected ?OptionTemplateValueEntity $defaultValue = null;

    protected ?OptionTemplateGroupTranslationCollection $translations = null;

    public function getTemplateId(): string
    {
        return $this->templateId;
    }

    public function setTemplateId(string $templateId): void
    {
        $this->templateId = $templateId;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getDefaultValueId(): ?string
    {
        return $this->defaultValueId;
    }

    public function setDefaultValueId(?string $defaultValueId): void
    {
        $this->defaultValueId = $defaultValueId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getTemplate(): ?OptionTemplateEntity
    {
        return $this->template;
    }

    public function setTemplate(?OptionTemplateEntity $template): void
    {
        $this->template = $template;
    }

    public function getValues(): ?OptionTemplateValueCollection
    {
        return $this->values;
    }

    public function setValues(OptionTemplateValueCollection $values): void
    {
        $this->values = $values;
    }

    public function getDefaultValue(): ?OptionTemplateValueEntity
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(?OptionTemplateValueEntity $defaultValue): void
    {
        $this->defaultValue = $defaultValue;
    }

    public function getTranslations(): ?OptionTemplateGroupTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(OptionTemplateGroupTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
