<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Localized product copy consumed by Next.js via `translated.name` / `translated.description`. */
final class ProductGridProductTranslatedStruct extends Struct
{
    public function __construct(
        protected string $name,
        protected ?string $description,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_product_grid_product_translated';
    }
}
