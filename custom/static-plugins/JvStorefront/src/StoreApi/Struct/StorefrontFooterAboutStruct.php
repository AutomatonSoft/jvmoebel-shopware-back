<?php declare(strict_types=1);

namespace Jv\Storefront\StoreApi\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class StorefrontFooterAboutStruct extends Struct
{
    public function __construct(
        protected ?string $eyebrow,
        protected string $title,
        protected string $description,
    ) {
    }

    public function getEyebrow(): ?string
    {
        return $this->eyebrow;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getApiAlias(): string
    {
        return 'jv_storefront_footer_about';
    }
}
