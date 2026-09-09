<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** One linked hotspot in `data.items[]`. */
final class ShopTheLookItemStruct extends Struct
{
    /**
     * @param array{x: float, y: float} $hotspot
     */
    public function __construct(
        protected string $id,
        protected int $position,
        protected string $name,
        protected ?string $description,
        protected string $url,
        protected array $hotspot,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /** @return array{x: float, y: float} */
    public function getHotspot(): array
    {
        return $this->hotspot;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_shop_the_look_item';
    }
}
