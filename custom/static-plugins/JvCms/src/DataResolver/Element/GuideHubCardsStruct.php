<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-guide-hub-cards`. */
final class GuideHubCardsStruct extends Struct
{
    /**
     * @param list<GuideHubCardStruct> $cards
     */
    public function __construct(
        protected string $title = '',
        protected ?string $eyebrow = null,
        protected array $cards = [],
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getEyebrow(): ?string
    {
        return $this->eyebrow;
    }

    /** @return list<GuideHubCardStruct> */
    public function getCards(): array
    {
        return $this->cards;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_guide_hub_cards';
    }
}
