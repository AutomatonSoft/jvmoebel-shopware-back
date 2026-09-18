<?php declare(strict_types=1);

namespace Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValueTranslation;

use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValue\OptionTemplateValueEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

final class OptionTemplateValueTranslationEntity extends TranslationEntity
{
    protected string $jvOptionTemplateValueId;

    protected ?string $name = null;

    protected ?OptionTemplateValueEntity $jvOptionTemplateValue = null;

    public function getJvOptionTemplateValueId(): string
    {
        return $this->jvOptionTemplateValueId;
    }

    public function setJvOptionTemplateValueId(string $jvOptionTemplateValueId): void
    {
        $this->jvOptionTemplateValueId = $jvOptionTemplateValueId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getJvOptionTemplateValue(): ?OptionTemplateValueEntity
    {
        return $this->jvOptionTemplateValue;
    }

    public function setJvOptionTemplateValue(?OptionTemplateValueEntity $jvOptionTemplateValue): void
    {
        $this->jvOptionTemplateValue = $jvOptionTemplateValue;
    }
}
