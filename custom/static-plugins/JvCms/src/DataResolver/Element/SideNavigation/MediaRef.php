<?php declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element\SideNavigation;

use Shopware\Core\Framework\Struct\Struct;

final class MediaRef extends Struct
{
    public function __construct(
        protected string $url,
        protected string $alt = '',
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getAlt(): string
    {
        return $this->alt;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_side_navigation_media_ref';
    }
}
