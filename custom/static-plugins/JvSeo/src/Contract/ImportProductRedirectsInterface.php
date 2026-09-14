<?php declare(strict_types=1);

namespace Jv\Seo\Contract;

use Shopware\Core\Framework\Context;

interface ImportProductRedirectsInterface
{
    /** @param iterable<ImportProductRedirectData> $redirects */
    public function import(iterable $redirects, Context $context): ProductRedirectImportResult;
}
