<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Store API `data` for `jv-related-look-cards`.
 */
final class RelatedLookCardsStruct extends Struct
{
    /**
     * @param list<RelatedLookCardStruct> $cards
     */
    public function __construct(
        protected string $title,
        protected array $cards,
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /** @return list<RelatedLookCardStruct> */
    public function getCards(): array
    {
        return $this->cards;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_related_look_cards';
    }
}
