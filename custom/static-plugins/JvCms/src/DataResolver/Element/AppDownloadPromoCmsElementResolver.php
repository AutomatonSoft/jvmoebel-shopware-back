<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Resolves CMS element `jv-app-download-promo` for the Store API (platform SPEC-033 / backend SPEC-041).
 */
final class AppDownloadPromoCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-app-download-promo';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $qrImageMediaId = $this->normalizeUuid($slot->getFieldConfig()->get('qrImageMedia')?->getValue());
        if (null === $qrImageMediaId) {
            return null;
        }

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add(
            'jv_app_download_promo_media_'.$slot->getUniqueIdentifier(),
            MediaDefinition::class,
            new Criteria([$qrImageMediaId]),
        );

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $qrImageMediaId = $this->normalizeUuid($config->get('qrImageMedia')?->getValue());
        $mediaEntity = null !== $qrImageMediaId
            ? ($this->mediaMap($result->get('jv_app_download_promo_media_'.$slot->getUniqueIdentifier()))[$qrImageMediaId] ?? null)
            : null;

        $slot->setData(new AppDownloadPromoStruct(
            title: $this->requiredString($config->get('title')?->getValue()),
            description: $this->requiredString($config->get('description')?->getValue()),
            appStoreUrl: $this->safeAbsoluteUrl($this->stringOrNull($config->get('appStoreUrl')?->getValue())),
            playStoreUrl: $this->safeAbsoluteUrl($this->stringOrNull($config->get('playStoreUrl')?->getValue())),
            qrImage: $this->resolveQrImage($mediaEntity),
            promoCode: $this->requiredString($config->get('promoCode')?->getValue()),
        ));
    }

    private function resolveQrImage(?MediaEntity $entity): ?AppDownloadPromoMediaStruct
    {
        if (!$entity instanceof MediaEntity) {
            return null;
        }

        $url = $entity->getUrl();
        if ('' === $url) {
            return null;
        }

        $alt = trim((string) ($entity->getTranslated()['alt'] ?? $entity->getAlt() ?? ''));

        return new AppDownloadPromoMediaStruct($url, $alt);
    }

    private function safeAbsoluteUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ('' === $url) {
            return null;
        }

        if (false === filter_var($url, \FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        if (!\is_array($parts)) {
            return null;
        }

        $schemeRaw = $parts['scheme'] ?? null;
        $scheme = \is_string($schemeRaw) ? strtolower($schemeRaw) : '';
        if (!\in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = $parts['host'] ?? null;
        if (!\is_string($host) || '' === $host) {
            return null;
        }

        return $url;
    }

    private function normalizeUuid(mixed $value): ?string
    {
        if (!\is_string($value) && !\is_int($value)) {
            return null;
        }

        $id = strtolower(trim((string) $value));
        if ('' === $id || !Uuid::isValid($id)) {
            return null;
        }

        return $id;
    }

    private function requiredString(mixed $value): string
    {
        return $this->stringOrNull($value) ?? '';
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            return null;
        }

        return trim((string) $value);
    }

    /**
     * @param EntitySearchResult<covariant EntityCollection<covariant Entity>>|null $searchResult
     *
     * @return array<string, MediaEntity>
     */
    private function mediaMap(?EntitySearchResult $searchResult): array
    {
        if (null === $searchResult) {
            return [];
        }

        $map = [];
        foreach ($searchResult->getEntities() as $entity) {
            if ($entity instanceof MediaEntity) {
                $map[$entity->getUniqueIdentifier()] = $entity;
            }
        }

        return $map;
    }
}
