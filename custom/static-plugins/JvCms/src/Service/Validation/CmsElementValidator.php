<?php declare(strict_types=1);

namespace Jv\Cms\Service\Validation;

final readonly class CmsElementValidator
{
    /** @var array<string, list<CmsElementValidationRuleInterface>> */
    private array $rules;

    /** @param iterable<CmsElementValidationRuleInterface> $rules */
    public function __construct(iterable $rules)
    {
        $rulesByElementType = [];

        foreach ($rules as $rule) {
            $rulesByElementType[$rule->elementType()][] = $rule;
        }

        $this->rules = $rulesByElementType;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<CmsElementValidationError>
     */
    public function validate(string $elementType, array $config): array
    {
        $errors = [];

        foreach ($this->rules[$elementType] ?? [] as $rule) {
            foreach ($rule->validate($config) as $error) {
                $errors[] = $error;
            }
        }

        return $errors;
    }
}
