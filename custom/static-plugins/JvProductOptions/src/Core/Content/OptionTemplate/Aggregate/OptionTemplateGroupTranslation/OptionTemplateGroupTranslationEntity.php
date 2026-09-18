<?php declare(strict_types=1);

namespace Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateGroupTranslation;

use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateGroup\OptionTemplateGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

final class OptionTemplateGroupTranslationEntity extends TranslationEntity
{
    protected string $jvOptionTemplateGroupId;

    protected ?string $name = null;

    protected ?OptionTemplateGroupEntity $jvOptionTemplateGroup = null;

    public function getJvOptionTemplateGroupId(): string
    {
        return $this->jvOptionTemplateGroupId;
    }

    public function setJvOptionTemplateGroupId(string $jvOptionTemplateGroupId): void
    {
        $this->jvOptionTemplateGroupId = $jvOptionTemplateGroupId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getJvOptionTemplateGroup(): ?OptionTemplateGroupEntity
    {
        return $this->jvOptionTemplateGroup;
    }

    public function setJvOptionTemplateGroup(?OptionTemplateGroupEntity $jvOptionTemplateGroup): void
    {
        $this->jvOptionTemplateGroup = $jvOptionTemplateGroup;
    }
}
