<?php declare(strict_types=1);

namespace Jv\Cms\Service\Validation;

interface CmsElementValidationRuleInterface
{
    public function elementType(): string;

    /**
     * @param array<string, mixed> $config
     *
     * @return list<CmsElementValidationError>
     */
    public function validate(array $config): array;
}
