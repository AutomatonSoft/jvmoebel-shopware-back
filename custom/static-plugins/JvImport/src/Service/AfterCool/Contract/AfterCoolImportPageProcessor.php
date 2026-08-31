<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool\Contract;

use Jv\Import\Service\AfterCool\AfterCoolPageProcessingResult;
use Shopware\Core\Framework\Context;

interface AfterCoolImportPageProcessor
{
    public function process(string $runId, int $offset, Context $context): AfterCoolPageProcessingResult;
}
