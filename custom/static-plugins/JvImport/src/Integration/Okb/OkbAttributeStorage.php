<?php declare(strict_types=1);

namespace Jv\Import\Integration\Okb;

enum OkbAttributeStorage: string
{
    case Property = 'property';
    case CustomField = 'custom_field';

    public static function fromFeatureRelevance(string $featureRelevance): self
    {
        foreach (['VARIATION_THEME', 'FILTER', 'NAVIGATION', 'SEARCH'] as $feature) {
            if (in_array($feature, explode('|', $featureRelevance), true)) {
                return self::Property;
            }
        }

        return self::CustomField;
    }
}
