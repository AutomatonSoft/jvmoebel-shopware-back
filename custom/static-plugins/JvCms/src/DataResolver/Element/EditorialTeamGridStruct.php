<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-editorial-team-grid`. */
final class EditorialTeamGridStruct extends Struct
{
    /**
     * @param list<EditorialTeamMemberStruct> $members
     */
    public function __construct(
        protected string $title = '',
        protected array $members = [],
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /** @return list<EditorialTeamMemberStruct> */
    public function getMembers(): array
    {
        return $this->members;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_editorial_team_grid';
    }
}
