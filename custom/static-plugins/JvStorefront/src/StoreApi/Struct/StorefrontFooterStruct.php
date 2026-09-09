<?php declare(strict_types=1);

namespace Jv\Storefront\StoreApi\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class StorefrontFooterStruct extends Struct
{
    /**
     * @param list<StorefrontNavigationItemStruct> $categoryNavigation
     * @param list<StorefrontNavigationItemStruct> $serviceNavigation
     * @param list<StorefrontSocialLinkStruct>     $socialLinks
     * @param list<StorefrontPaymentBadgeStruct>   $paymentBadges
     */
    public function __construct(
        protected StorefrontFooterAboutStruct $about,
        protected StorefrontFooterRevocationStruct $revocation,
        protected ?string $copyrightText,
        protected array $categoryNavigation,
        protected array $serviceNavigation,
        protected array $socialLinks,
        protected array $paymentBadges,
    ) {
    }

    public function getAbout(): StorefrontFooterAboutStruct
    {
        return $this->about;
    }

    public function getRevocation(): StorefrontFooterRevocationStruct
    {
        return $this->revocation;
    }

    public function getCopyrightText(): ?string
    {
        return $this->copyrightText;
    }

    /** @return list<StorefrontNavigationItemStruct> */
    public function getCategoryNavigation(): array
    {
        return $this->categoryNavigation;
    }

    /** @return list<StorefrontNavigationItemStruct> */
    public function getServiceNavigation(): array
    {
        return $this->serviceNavigation;
    }

    /** @return list<StorefrontSocialLinkStruct> */
    public function getSocialLinks(): array
    {
        return $this->socialLinks;
    }

    /** @return list<StorefrontPaymentBadgeStruct> */
    public function getPaymentBadges(): array
    {
        return $this->paymentBadges;
    }

    public function getApiAlias(): string
    {
        return 'jv_storefront_footer';
    }
}
