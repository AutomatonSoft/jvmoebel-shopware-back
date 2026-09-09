<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

/**
 * Resolves CMS element `jv-why-jvmoebel` for the Store API (platform SPEC-013 / backend SPEC-016).
 *
 * Flat static config only — no DAL. Invalid URLs and incomplete benefits are skipped.
 * Bad config must not HTTP 500.
 */
final class WhyJvmoebelCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-why-jvmoebel';

    private const array ICONS = [
        'advice',
        'design',
        'payment',
    ];

    public function getType(): string
    {
        return self::TYPE;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();

        $slot->setData(new WhyJvmoebelStruct(
            mark: $this->requiredString($config->get('mark')?->getValue()),
            tagline: $this->requiredString($config->get('tagline')?->getValue()),
            title: $this->requiredString($config->get('title')?->getValue()),
            eyebrow: $this->optionalString($config->get('eyebrow')?->getValue()),
            description: $this->optionalString($config->get('description')?->getValue()),
            benefits: $this->normalizeBenefits($config->get('benefits')?->getValue()),
            viewAll: $this->normalizeViewAll($config->get('viewAll')?->getValue()),
        ));
    }

    /**
     * @return list<WhyJvmoebelBenefitStruct>
     */
    private function normalizeBenefits(mixed $value): array
    {
        $entries = $this->benefitConfigEntries($value);

        usort($entries, function (array $a, array $b): int {
            $positionA = $this->resolvePosition($a['item']['position'] ?? null, $a['index']);
            $positionB = $this->resolvePosition($b['item']['position'] ?? null, $b['index']);
            if ($positionA !== $positionB) {
                return $positionA <=> $positionB;
            }

            return $a['index'] <=> $b['index'];
        });

        $normalized = [];
        $seenIds = [];

        foreach ($entries as $entry) {
            $item = $entry['item'];
            $originalIndex = $entry['index'];

            $icon = $this->normalizeIcon($item['icon'] ?? null);
            if (null === $icon) {
                continue;
            }

            $title = $this->requiredString($item['title'] ?? null);
            $description = $this->requiredString($item['description'] ?? null);
            $url = $this->safeWhyJvmoebelHref(\is_string($item['url'] ?? null) ? $item['url'] : null);
            if ('' === $title || '' === $description || null === $url) {
                continue;
            }

            $configId = $this->requiredString($item['id'] ?? null);
            $id = '' !== $configId ? $configId : $title.'-'.$originalIndex;
            if (isset($seenIds[$id])) {
                continue;
            }

            $seenIds[$id] = true;

            $normalized[] = new WhyJvmoebelBenefitStruct(
                id: $id,
                position: $this->resolvePosition($item['position'] ?? null, $originalIndex),
                icon: $icon,
                title: $title,
                description: $description,
                url: $url,
            );
        }

        return $normalized;
    }

    private function normalizeViewAll(mixed $value): ?WhyJvmoebelLinkStruct
    {
        if (!\is_array($value)) {
            return null;
        }

        $label = $this->requiredString($value['label'] ?? null);
        $url = $this->safeWhyJvmoebelHref(\is_string($value['url'] ?? null) ? $value['url'] : null);
        if ('' === $label || null === $url) {
            return null;
        }

        return new WhyJvmoebelLinkStruct($label, $url);
    }

    private function normalizeIcon(mixed $value): ?string
    {
        $icon = $this->requiredString($value);

        return \in_array($icon, self::ICONS, true) ? $icon : null;
    }

    /**
     * @return list<array{index: int, item: array<string, mixed>}>
     */
    private function benefitConfigEntries(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        if (!array_is_list($value)) {
            $value = array_values($value);
        }

        $entries = [];
        foreach ($value as $index => $item) {
            if (!\is_array($item)) {
                continue;
            }

            $entries[] = [
                'index' => $index,
                'item' => $item,
            ];
        }

        return $entries;
    }

    private function resolvePosition(mixed $value, int $originalIndex): int
    {
        if (\is_int($value) || \is_float($value)) {
            $position = (float) $value;
            if (is_finite($position)) {
                return (int) $position;
            }
        }

        return $originalIndex;
    }

    /**
     * Root-relative `/path` (not `//…`) or absolute http(s) with a host.
     */
    private function safeWhyJvmoebelHref(?string $href): ?string
    {
        $href = trim((string) $href);
        if ('' === $href) {
            return null;
        }

        if (str_starts_with($href, '/') && !str_starts_with($href, '//')) {
            return $href;
        }

        if (false === filter_var($href, \FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($href);
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

        return $href;
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
}
