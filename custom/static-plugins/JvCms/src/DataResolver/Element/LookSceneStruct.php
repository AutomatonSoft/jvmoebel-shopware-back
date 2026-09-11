<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-look-scene`. */
final class LookSceneStruct extends Struct
{
    /**
     * @param list<LookSceneProductStruct> $products
     */
    public function __construct(
        protected string $title,
        protected ?string $description,
        protected ?LookSceneMediaStruct $image,
        protected array $products,
        protected ?LookSceneLinkStruct $viewAll,
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getImage(): ?LookSceneMediaStruct
    {
        return $this->image;
    }

    /** @return list<LookSceneProductStruct> */
    public function getProducts(): array
    {
        return $this->products;
    }

    public function getViewAll(): ?LookSceneLinkStruct
    {
        return $this->viewAll;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_look_scene';
    }
}
