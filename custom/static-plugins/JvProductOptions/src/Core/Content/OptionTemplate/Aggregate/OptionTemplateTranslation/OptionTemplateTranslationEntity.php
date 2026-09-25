<?php declare(strict_types=1);

namespace Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateTranslation;

use Jv\ProductOptions\Core\Content\OptionTemplate\OptionTemplateEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

final class OptionTemplateTranslationEntity extends TranslationEntity
{
    protected string $jvOptionTemplateId;

    protected ?string $name = null;

    protected ?OptionTemplateEntity $jvOptionTemplate = null;

    public function getJvOptionTemplateId(): string
    {
        return $this->jvOptionTemplateId;
    }

    public function setJvOptionTemplateId(string $jvOptionTemplateId): void
    {
        $this->jvOptionTemplateId = $jvOptionTemplateId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getJvOptionTemplate(): ?OptionTemplateEntity
    {
        return $this->jvOptionTemplate;
    }

    public function setJvOptionTemplate(?OptionTemplateEntity $jvOptionTemplate): void
    {
        $this->jvOptionTemplate = $jvOptionTemplate;
    }
}
