<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-app-download-promo`. */
final class AppDownloadPromoStruct extends Struct
{
    public function __construct(
        protected string $title,
        protected string $description,
        protected ?string $appStoreUrl,
        protected ?string $playStoreUrl,
        protected ?AppDownloadPromoMediaStruct $qrImage,
        protected string $promoCode,
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getAppStoreUrl(): ?string
    {
        return $this->appStoreUrl;
    }

    public function getPlayStoreUrl(): ?string
    {
        return $this->playStoreUrl;
    }

    public function getQrImage(): ?AppDownloadPromoMediaStruct
    {
        return $this->qrImage;
    }

    public function getPromoCode(): string
    {
        return $this->promoCode;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_app_download_promo';
    }
}
