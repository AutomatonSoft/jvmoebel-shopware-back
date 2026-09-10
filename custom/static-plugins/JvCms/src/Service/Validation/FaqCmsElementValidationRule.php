<?php declare(strict_types=1);

namespace Jv\Cms\Service\Validation;

final class FaqCmsElementValidationRule implements CmsElementValidationRuleInterface
{
    private const ELEMENT_TYPE = 'jv-faq';

    private const QUESTION_REQUIRED = 'JV_CMS_FAQ_QUESTION_REQUIRED';

    private const ANSWER_REQUIRED = 'JV_CMS_FAQ_ANSWER_REQUIRED';

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

        $errors = [];
        foreach ($items as $index => $item) {
            if (!\is_array($item)) {
                continue;
            }

            $path = sprintf('/config/items/value/%s', $index);
            if (!$this->isFilledString($item['question'] ?? null)) {
                $errors[] = new CmsElementValidationError(
                    fieldPath: $path.'/question',
                    message: 'Enter a question.',
                    code: self::QUESTION_REQUIRED,
                );
            }

            if (!$this->isFilledString($item['answer'] ?? null)) {
                $errors[] = new CmsElementValidationError(
                    fieldPath: $path.'/answer',
                    message: 'Enter an answer.',
                    code: self::ANSWER_REQUIRED,
                );
            }
        }

        return $errors;
    }

    private function isFilledString(mixed $value): bool
    {
        return \is_string($value) && '' !== trim($value);
    }
}
