<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Resolved product entry for `jv-look-scene`. */
final class LookSceneProductStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected int $position,
        protected string $name,
        protected string $url,
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

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_look_scene_product';
    }
}
