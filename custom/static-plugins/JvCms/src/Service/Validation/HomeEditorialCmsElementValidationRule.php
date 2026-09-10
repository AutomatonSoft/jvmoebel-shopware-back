<?php declare(strict_types=1);

namespace Jv\Cms\Service\Validation;

final class HomeEditorialCmsElementValidationRule implements CmsElementValidationRuleInterface
{
    private const ELEMENT_TYPE = 'jv-home-editorial';

    private const PARAGRAPH_REQUIRED = 'JV_CMS_HOME_EDITORIAL_PARAGRAPH_REQUIRED';

    public function elementType(): string
    {
        return self::ELEMENT_TYPE;
    }

    public function validate(array $config): array
    {
        $sectionsConfig = $config['sections'] ?? null;
        if (!\is_array($sectionsConfig)) {
            return [];
        }

        $sections = $sectionsConfig['value'] ?? null;
        if (!\is_array($sections)) {
            return [];
        }

        $errors = [];
        foreach ($sections as $sectionIndex => $section) {
            if (!\is_array($section)) {
                continue;
            }

            $paragraphs = $section['paragraphs'] ?? [];
            if (!\is_array($paragraphs)) {
                $paragraphs = [];
            }

            if ($this->hasFilledParagraph($paragraphs)) {
                continue;
            }

            $path = sprintf('/config/sections/value/%s/paragraphs', $sectionIndex);
            if ([] === $paragraphs) {
                $errors[] = $this->paragraphError($path);

                continue;
            }

            foreach (array_keys($paragraphs) as $paragraphIndex) {
                $errors[] = $this->paragraphError(sprintf('%s/%s', $path, $paragraphIndex));
            }
        }

        return $errors;
    }

    /** @param array<array-key, mixed> $paragraphs */
    private function hasFilledParagraph(array $paragraphs): bool
    {
        foreach ($paragraphs as $paragraph) {
            if (\is_string($paragraph) && '' !== trim($paragraph)) {
                return true;
            }
        }

        return false;
    }

    private function paragraphError(string $fieldPath): CmsElementValidationError
    {
        return new CmsElementValidationError(
            fieldPath: $fieldPath,
            message: 'Add and fill in at least one paragraph.',
            code: self::PARAGRAPH_REQUIRED,
        );
    }
}
