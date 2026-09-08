<?php declare(strict_types=1);

namespace Jv\Import\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class AfterCoolImportPageMessage implements AsyncMessageInterface
{
    public function __construct(public string $runId, public int $offset)
    {
        if (!Uuid::isValid($runId) || 0 > $offset || 0 !== $offset % 100) {
            throw new \InvalidArgumentException('Aftercool import message has an invalid run or offset.');
        }
    }
}
