<?php declare(strict_types=1);

namespace Jv\Seo\Service\Redirect\Exception;

final class RedirectValidationException extends \InvalidArgumentException
{
    /** @param list<array{field: string, message: string}> $violations */
    public function __construct(private readonly array $violations)
    {
        parent::__construct($violations[0]['message'] ?? 'Redirect data is invalid.');
    }

    /** @return list<array{field: string, message: string}> */
    public function violations(): array
    {
        return $this->violations;
    }
}
