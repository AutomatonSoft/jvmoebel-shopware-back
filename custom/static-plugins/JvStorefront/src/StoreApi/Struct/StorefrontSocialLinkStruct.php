<?php declare(strict_types=1);

namespace Jv\Storefront\StoreApi\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class StorefrontSocialLinkStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected string $label,
        protected string $url,
        protected bool $openInNewTab,
        protected int $position,
        protected StorefrontMediaStruct $icon,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function isOpenInNewTab(): bool
    {
        return $this->openInNewTab;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getIcon(): StorefrontMediaStruct
    {
        return $this->icon;
    }

    public function getApiAlias(): string
    {
        return 'jv_storefront_footer_social_link';
    }
}
