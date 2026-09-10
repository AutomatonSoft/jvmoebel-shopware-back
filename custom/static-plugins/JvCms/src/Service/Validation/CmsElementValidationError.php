<?php declare(strict_types=1);

namespace Jv\Cms\Service\Validation;

final readonly class CmsElementValidationError
{
    public function __construct(
        public string $fieldPath,
        public string $message,
        public string $code,
        public bool $blockSave = false,
    ) {
    }
}
