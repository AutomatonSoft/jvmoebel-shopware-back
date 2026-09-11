<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** One team member in Store API `data.members[]`. */
final class EditorialTeamMemberStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected int $position,
        protected string $name,
        protected ?string $role,
        protected string $url,
        protected ?EditorialTeamMemberMediaStruct $image = null,
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

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getImage(): ?EditorialTeamMemberMediaStruct
    {
        return $this->image;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_editorial_team_grid_member';
    }
}
