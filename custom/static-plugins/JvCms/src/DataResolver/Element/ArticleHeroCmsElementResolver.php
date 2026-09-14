<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Resolves `jv-article-hero` for Store API (platform SPEC-022 / backend SPEC-030).
 *
 * Persisted config is untrusted. Invalid media UUIDs never reach DAL.
 * Invalid dates and read times become null. Bad config must not HTTP 500.
 */
final class ArticleHeroCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-article-hero';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $imageMediaId = $this->normalizeUuid($slot->getFieldConfig()->get('imageMedia')?->getValue());
        if (null === $imageMediaId) {
            return null;
        }

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add(
            $this->mediaResultKey($slot),
            MediaDefinition::class,
            new Criteria([$imageMediaId]),
        );

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $slot->setData(new ArticleHeroStruct(
            title: $this->requiredString($config->get('title')?->getValue()),
            eyebrow: $this->optionalString($config->get('eyebrow')?->getValue()),
            description: $this->optionalString($config->get('description')?->getValue()),
            publishedAt: $this->normalizePublishedAt($config->get('publishedAt')?->getValue()),
            readTimeMinutes: $this->normalizeReadTimeMinutes($config->get('readTimeMinutes')?->getValue()),
            image: $this->resolveImage($result->get($this->mediaResultKey($slot))),
        ));
    }

    private function normalizePublishedAt(mixed $value): ?string
    {
        $string = $this->requiredString($value);
        if ('' === $string) {
            return null;
        }

        if (false === strtotime($string)) {
            return null;
        }

        return $string;
    }

    private function normalizeReadTimeMinutes(mixed $value): ?int
    {
        if (\is_int($value)) {
            return $value >= 1 ? $value : null;
        }

        if (\is_float($value) && is_finite($value)) {
            $minutes = (int) $value;

            return $minutes >= 1 ? $minutes : null;
        }

        if (\is_string($value)) {
            $trimmed = trim($value);
            if ('' === $trimmed || !ctype_digit($trimmed)) {
                return null;
            }

            $minutes = (int) $trimmed;

            return $minutes >= 1 ? $minutes : null;
        }

        return null;
    }

    /**
     * @param EntitySearchResult<covariant EntityCollection<covariant Entity>>|null $searchResult
     */
    private function resolveImage(?EntitySearchResult $searchResult): ?ArticleHeroMediaStruct
    {
        if (null === $searchResult) {
            return null;
        }

        $entities = $searchResult->getEntities();
        if (!$entities instanceof MediaCollection) {
            return null;
        }

        $entity = $entities->first();
        if (!$entity instanceof MediaEntity || '' === $entity->getUrl()) {
            return null;
        }

        $alt = trim((string) ($entity->getTranslated()['alt'] ?? $entity->getFileName() ?? ''));

        return new ArticleHeroMediaStruct($entity->getUrl(), $alt);
    }

    private function normalizeUuid(mixed $value): ?string
    {
        if (!\is_string($value) && !\is_int($value)) {
            return null;
        }

        $id = strtolower(trim((string) $value));

        return '' !== $id && Uuid::isValid($id) ? $id : null;
    }

    private function requiredString(mixed $value): string
    {
        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            return '';
        }

        return trim((string) $value);
    }

    private function optionalString(mixed $value): ?string
    {
        $string = $this->requiredString($value);

        return '' === $string ? null : $string;
    }

    private function mediaResultKey(CmsSlotEntity $slot): string
    {
        return 'jv_article_hero_media_'.$slot->getUniqueIdentifier();
    }
}
