<?php declare(strict_types=1);

namespace Jv\Seo\Contract;

use Shopware\Core\Framework\Context;

interface ImportImageRedirectsInterface
{
    /** @param iterable<ImportImageRedirectData> $redirects */
    public function import(iterable $redirects, Context $context): ImageRedirectImportResult;
}
