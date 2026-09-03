<?php

declare(strict_types=1);

namespace Jv\Cms\Service\Search\Exception;

/**
 * OpenSearch / listing infrastructure failure at the Store API boundary.
 */
final class SearchUnavailableException extends \RuntimeException
{
    public function __construct(string $message = 'Product search is temporarily unavailable.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
