<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-expert-profile`. */
final class ExpertProfileStruct extends Struct
{
    public function __construct(
        protected string $name = '',
        protected ?string $role = null,
        protected ?string $bio = null,
        protected ?ExpertProfileMediaStruct $image = null,
        protected ?ExpertProfileLinkStruct $link = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function getImage(): ?ExpertProfileMediaStruct
    {
        return $this->image;
    }

    public function getLink(): ?ExpertProfileLinkStruct
    {
        return $this->link;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_expert_profile';
    }
}
