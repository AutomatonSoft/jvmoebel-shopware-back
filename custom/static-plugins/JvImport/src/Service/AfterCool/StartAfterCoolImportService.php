<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool;

use Jv\Import\Message\AfterCoolImportPageMessage;
use Jv\Import\Service\AfterCool\Contract\AfterCoolImportRunStore;
use Jv\Import\Service\AfterCool\Contract\AfterCoolProductSourceInterface;
use Jv\Import\Service\AfterCool\Exception\AfterCoolFactoryNotFoundException;
use Shopware\Core\Framework\Context;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class StartAfterCoolImportService
{
    public function __construct(
        private AfterCoolProductSourceInterface $source,
        private AfterCoolImportRunStore $runs,
        private MessageBusInterface $messageBus,
        private LockFactory $lockFactory,
    ) {
    }

    public function start(int $factoryId, Context $context): string
    {
        $lock = $this->lockFactory->createLock('jv-aftercool-factory-'.$factoryId, 10.0);
        $lock->acquire(true);
        try {
            foreach ($this->source->getFactories() as $factory) {
                if ($factory->id === $factoryId) {
                    $runId = $this->runs->createQueued($factory->id, $factory->name, 'JV:lister:'.$factory->id, $context);
                    try {
                        $this->messageBus->dispatch(new AfterCoolImportPageMessage($runId, 0));
                    } catch (\Throwable $exception) {
                        $this->runs->markFailed($runId, 'messenger_dispatch_failed', 'The import could not be queued.', $context);
                        throw $exception;
                    }

                    return $runId;
                }
            }
            throw new AfterCoolFactoryNotFoundException('Aftercool factory was not found.');
        } finally {
            $lock->release();
        }
    }
}
