<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Jv\Cms\DataResolver\Element\Hero\HeroSlide;
use Shopware\Core\Framework\Struct\Struct;

final class HeroStruct extends Struct
{
    /**
     * @param list<HeroSlide> $slides
     */
    public function __construct(
        protected ?string $ariaLabel = null,
        protected bool $autoplay = true,
        protected int $autoplayIntervalMs = 7000,
        protected array $slides = [],
    ) {
    }

    public function getAriaLabel(): ?string
    {
        return $this->ariaLabel;
    }

    public function isAutoplay(): bool
    {
        return $this->autoplay;
    }

    public function getAutoplayIntervalMs(): int
    {
        return $this->autoplayIntervalMs;
    }

    /**
     * @return list<HeroSlide>
     */
    public function getSlides(): array
    {
        return $this->slides;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_hero';
    }
}
