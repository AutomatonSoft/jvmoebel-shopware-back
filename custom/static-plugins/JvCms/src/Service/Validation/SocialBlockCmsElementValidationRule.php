<?php declare(strict_types=1);

namespace Jv\Cms\Service\Validation;

final class SocialBlockCmsElementValidationRule implements CmsElementValidationRuleInterface
{
    private const ELEMENT_TYPE = 'jv-social-block';

    private const ITEM_REQUIRED = 'JV_CMS_SOCIAL_BLOCK_ITEM_REQUIRED';

    private const URL_REQUIRED = 'JV_CMS_SOCIAL_BLOCK_URL_REQUIRED';

    public function elementType(): string
    {
        return self::ELEMENT_TYPE;
    }

    public function validate(array $config): array
    {
        $itemsConfig = $config['items'] ?? null;
        if (!\is_array($itemsConfig)) {
            return [];
        }

        $items = $itemsConfig['value'] ?? null;
        if (!\is_array($items)) {
            return [];
        }

        if ([] === $items) {
            return [new CmsElementValidationError(
                fieldPath: '/config/items/value',
                message: 'Add at least one social channel.',
                code: self::ITEM_REQUIRED,
            )];
        }

        $errors = [];
        foreach ($items as $index => $item) {
            if (!\is_array($item) || $this->isFilledString($item['url'] ?? null)) {
                continue;
            }

            $errors[] = new CmsElementValidationError(
                fieldPath: sprintf('/config/items/value/%s/url', $index),
                message: 'Enter a link for the social channel.',
                code: self::URL_REQUIRED,
            );
        }

        return $errors;
    }

    private function isFilledString(mixed $value): bool
    {
        return \is_string($value) && '' !== trim($value);
    }
}
