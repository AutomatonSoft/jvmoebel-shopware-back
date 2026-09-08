<?php declare(strict_types=1);

namespace Jv\Storefront\Service;

use Jv\Storefront\Core\Content\StorefrontPaymentBadge\StorefrontPaymentBadgeCollection;
use Jv\Storefront\Core\Content\StorefrontPaymentBadge\StorefrontPaymentBadgeEntity;
use Jv\Storefront\Core\Content\StorefrontSocialLink\StorefrontSocialLinkCollection;
use Jv\Storefront\Core\Content\StorefrontSocialLink\StorefrontSocialLinkEntity;
use Jv\Storefront\StoreApi\Struct\StorefrontBrandingStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontConfigStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontFooterAboutStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontFooterRevocationStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontFooterStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontHeaderStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontLogoStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontMediaStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontNavigationItemStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontPaymentBadgeStruct;
use Jv\Storefront\StoreApi\Struct\StorefrontSocialLinkStruct;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class StorefrontConfigLoader
{
    private const int HEADER_NAVIGATION_DEPTH = 1;
    private const int FOOTER_CATEGORY_NAVIGATION_DEPTH = 2;
    private const int FOOTER_SERVICE_NAVIGATION_DEPTH = 1;

    /**
     * @param EntityRepository<StorefrontSocialLinkCollection>   $socialLinkRepository
     * @param EntityRepository<StorefrontPaymentBadgeCollection> $paymentBadgeRepository
     * @param EntityRepository<MediaCollection>                  $mediaRepository
     * @param EntityRepository<SalesChannelCollection>           $salesChannelRepository
     */
    public function __construct(
        private readonly EntityRepository $socialLinkRepository,
        private readonly EntityRepository $paymentBadgeRepository,
        private readonly EntityRepository $mediaRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly NavigationLoaderInterface $navigationLoader,
        private readonly StorefrontInputNormalizer $normalizer,
    ) {
    }

    public function load(SalesChannelContext $context): StorefrontConfigStruct
    {
        $salesChannel = $context->getSalesChannel();
        $customFields = $this->resolveStorefrontCustomFields($salesChannel, $context);

        return new StorefrontConfigStruct(
            header: new StorefrontHeaderStruct(
                branding: $this->loadBranding($salesChannel, $customFields, $context),
                navigation: $this->loadNavigation($salesChannel->getNavigationCategoryId(), self::HEADER_NAVIGATION_DEPTH, $context),
            ),
            footer: new StorefrontFooterStruct(
                about: $this->loadAbout($customFields),
                revocation: $this->loadRevocation($customFields),
                copyrightText: $this->normalizer->optionalString($customFields['jv_footer_copyright_text'] ?? null),
                categoryNavigation: $this->loadNavigation($salesChannel->getFooterCategoryId(), self::FOOTER_CATEGORY_NAVIGATION_DEPTH, $context),
                serviceNavigation: $this->loadNavigation($salesChannel->getServiceCategoryId(), self::FOOTER_SERVICE_NAVIGATION_DEPTH, $context),
                socialLinks: $this->loadSocialLinks($salesChannel->getId(), $context),
                paymentBadges: $this->loadPaymentBadges($salesChannel->getId(), $context),
            ),
        );
    }

    /**
     * Storefront settings are edited once per sales channel (default language in Admin).
     * Custom fields are TranslatedField values, so resolve the sales channel default language
     * instead of the request language to avoid stale per-language copies (e.g. Deutsch vs JVMöbel Deutschland).
     *
     * @return array<string, mixed>
     */
    private function resolveStorefrontCustomFields(SalesChannelEntity $salesChannel, SalesChannelContext $context): array
    {
        $defaultLanguageId = $salesChannel->getLanguageId();
        if ($defaultLanguageId === $context->getLanguageId()) {
            return $salesChannel->getCustomFields() ?? [];
        }

        $criteria = (new Criteria([$salesChannel->getId()]))
            ->addAssociation('translations');

        $loaded = $this->salesChannelRepository->search($criteria, $context->getContext())->first();
        if (!$loaded instanceof SalesChannelEntity) {
            return $salesChannel->getCustomFields() ?? [];
        }

        foreach ($loaded->getTranslations() ?? [] as $translation) {
            if ($translation->getLanguageId() === $defaultLanguageId) {
                return $translation->getCustomFields() ?? [];
            }
        }

        return $salesChannel->getCustomFields() ?? [];
    }

    /**
     * @param array<string, mixed> $customFields
     */
    private function loadBranding(SalesChannelEntity $salesChannel, array $customFields, SalesChannelContext $context): StorefrontBrandingStruct
    {
        $name = $this->normalizer->nonEmptyString($salesChannel->getTranslation('name') ?? $salesChannel->getName());
        if ('' === $name) {
            $name = 'JVMöbel';
        }

        $logoMediaId = $this->normalizer->normalizeUuid($customFields['jv_storefront_logo_media_id'] ?? null);
        $logo = null;
        if (null !== $logoMediaId) {
            $media = $this->mediaRepository->search(new Criteria([$logoMediaId]), $context->getContext())->first();
            if ($media instanceof MediaEntity) {
                $logo = $this->resolveLogo($media, $name);
            }
        }

        return new StorefrontBrandingStruct($name, $logo);
    }

    private function resolveLogo(MediaEntity $media, string $fallbackAlt): ?StorefrontLogoStruct
    {
        $url = $media->getUrl();
        if ('' === $url) {
            return null;
        }

        $width = (int) ($media->getMetaData()['width'] ?? 0);
        $height = (int) ($media->getMetaData()['height'] ?? 0);
        if ($width < 1 || $height < 1) {
            return null;
        }

        $alt = $this->normalizer->nonEmptyString($media->getTranslated()['alt'] ?? $media->getFileName());
        if ('' === $alt) {
            $alt = $fallbackAlt;
        }

        return new StorefrontLogoStruct($url, $alt, $width, $height);
    }

    /**
     * @param array<string, mixed> $customFields
     */
    private function loadAbout(array $customFields): StorefrontFooterAboutStruct
    {
        return new StorefrontFooterAboutStruct(
            eyebrow: $this->normalizer->optionalString($customFields['jv_footer_about_eyebrow'] ?? null),
            title: $this->normalizer->nonEmptyString($customFields['jv_footer_about_title'] ?? null),
            description: $this->normalizer->optionalString($customFields['jv_footer_about_description'] ?? null) ?? '',
        );
    }

    /**
     * @param array<string, mixed> $customFields
     */
    private function loadRevocation(array $customFields): StorefrontFooterRevocationStruct
    {
        return new StorefrontFooterRevocationStruct(
            enabled: $this->normalizer->boolValue($customFields['jv_footer_revocation_enabled'] ?? null),
            buttonLabel: $this->normalizer->optionalString($customFields['jv_footer_revocation_button_label'] ?? null),
            recipientEmail: $this->normalizer->safeEmail(isset($customFields['jv_footer_revocation_recipient_email']) ? (string) $customFields['jv_footer_revocation_recipient_email'] : null),
        );
    }

    /**
     * @return list<StorefrontNavigationItemStruct>
     */
    private function loadNavigation(?string $rootCategoryId, int $depth, SalesChannelContext $context): array
    {
        $rootId = $this->normalizer->normalizeUuid($rootCategoryId);
        if (null === $rootId) {
            return [];
        }

        try {
            $tree = $this->navigationLoader->load($rootId, $context, $rootId, max(0, $depth - 1));
        } catch (CategoryNotFoundException) {
            return [];
        }

        return $this->mapTreeItems($tree->getTree(), $depth);
    }

    /**
     * @param list<TreeItem> $treeItems
     *
     * @return list<StorefrontNavigationItemStruct>
     */
    private function mapTreeItems(array $treeItems, int $remainingDepth): array
    {
        if ($remainingDepth < 1) {
            return [];
        }

        $normalized = [];

        foreach ($treeItems as $treeItem) {
            $category = $treeItem->getCategory();
            $label = $this->categoryLabel($category);
            $href = $this->categoryHref($category);
            if ('' === $label || null === $href) {
                continue;
            }

            $children = $this->mapTreeItems($treeItem->getChildren(), $remainingDepth - 1);

            $normalized[] = new StorefrontNavigationItemStruct(
                id: $category->getId(),
                label: $label,
                href: $href,
                children: $children,
            );
        }

        return $normalized;
    }

    private function categoryLabel(CategoryEntity $category): string
    {
        $translated = $category->getTranslation('name');
        if (\is_string($translated) && '' !== trim($translated)) {
            return trim($translated);
        }

        return trim($category->getName() ?? '');
    }

    private function categoryHref(CategoryEntity $category): ?string
    {
        if (CategoryDefinition::TYPE_LINK === $category->getType()) {
            $linkType = $category->getTranslation('linkType') ?? $category->getLinkType();
            if (CategoryDefinition::LINK_TYPE_EXTERNAL === $linkType) {
                $externalLink = $category->getTranslation('externalLink') ?? $category->getExternalLink();
                if (\is_string($externalLink) && '' !== trim($externalLink)) {
                    return $this->normalizer->safeHref(trim($externalLink));
                }

                return null;
            }
        }

        if ($category instanceof SalesChannelCategoryEntity) {
            $seoUrl = $category->getSeoUrl();
            if (\is_string($seoUrl) && '' !== $seoUrl) {
                if (str_contains($seoUrl, SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER)) {
                    return $seoUrl;
                }

                return $this->normalizer->safeHref($seoUrl);
            }
        }

        $externalLink = $category->getTranslation('externalLink') ?? $category->getExternalLink();
        if (\is_string($externalLink) && '' !== trim($externalLink)) {
            return $this->normalizer->safeHref(trim($externalLink));
        }

        if (CategoryDefinition::TYPE_FOLDER === $category->getType()) {
            return null;
        }

        return $this->normalizer->safeHref('/navigation/'.$category->getId());
    }

    /**
     * @return list<StorefrontSocialLinkStruct>
     */
    private function loadSocialLinks(string $salesChannelId, SalesChannelContext $context): array
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannelId', $salesChannelId))
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('position', FieldSorting::ASCENDING))
            ->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING))
            ->addAssociation('iconMedia');

        $entities = $this->socialLinkRepository->search($criteria, $context->getContext())->getEntities();
        $normalized = [];

        foreach ($entities as $entity) {
            $item = $this->normalizeSocialLink($entity);
            if (null !== $item) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }

    private function normalizeSocialLink(StorefrontSocialLinkEntity $entity): ?StorefrontSocialLinkStruct
    {
        $label = $this->normalizer->nonEmptyString($entity->getLabel());
        $url = $this->normalizer->safeSocialUrl($entity->getUrl());
        $icon = $this->resolveMedia($entity->getIconMedia(), $label);
        if ('' === $label || null === $url || null === $icon) {
            return null;
        }

        return new StorefrontSocialLinkStruct(
            id: $entity->getId(),
            label: $label,
            url: $url,
            openInNewTab: $entity->isOpenInNewTab(),
            position: $entity->getPosition(),
            icon: $icon,
        );
    }

    /**
     * @return list<StorefrontPaymentBadgeStruct>
     */
    private function loadPaymentBadges(string $salesChannelId, SalesChannelContext $context): array
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannelId', $salesChannelId))
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('position', FieldSorting::ASCENDING))
            ->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING))
            ->addAssociation('iconMedia');

        $entities = $this->paymentBadgeRepository->search($criteria, $context->getContext())->getEntities();
        $normalized = [];

        foreach ($entities as $entity) {
            $item = $this->normalizePaymentBadge($entity);
            if (null !== $item) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }

    private function normalizePaymentBadge(StorefrontPaymentBadgeEntity $entity): ?StorefrontPaymentBadgeStruct
    {
        $label = $this->normalizer->nonEmptyString($entity->getLabel());
        $icon = $this->resolveMedia($entity->getIconMedia(), $label);
        if ('' === $label || null === $icon) {
            return null;
        }

        return new StorefrontPaymentBadgeStruct(
            id: $entity->getId(),
            label: $label,
            position: $entity->getPosition(),
            icon: $icon,
        );
    }

    private function resolveMedia(?MediaEntity $media, string $fallbackAlt): ?StorefrontMediaStruct
    {
        if (null === $media) {
            return null;
        }

        $url = $media->getUrl();
        if ('' === $url) {
            return null;
        }

        $alt = $this->normalizer->nonEmptyString($media->getTranslated()['alt'] ?? $media->getFileName());
        if ('' === $alt) {
            $alt = $fallbackAlt;
        }

        return new StorefrontMediaStruct($url, $alt);
    }
}
