<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-countdown-promo`. */
final class CountdownPromoStruct extends Struct
{
    public function __construct(
        protected string $title,
        protected ?string $eyebrow,
        protected ?string $description,
        protected ?string $endsAt,
        protected string $promoCode,
        protected ?CountdownPromoLinkStruct $link,
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getEndsAt(): ?string
    {
        return $this->endsAt;
    }

    public function getPromoCode(): string
    {
        return $this->promoCode;
    }

    public function getLink(): ?CountdownPromoLinkStruct
    {
        return $this->link;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_countdown_promo';
    }
}
