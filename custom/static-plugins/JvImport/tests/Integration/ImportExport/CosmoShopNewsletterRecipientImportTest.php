<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\ImportExport;

require_once __DIR__.'/AbstractCosmoShopImportExportTestCase.php';

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterUnsubscribeEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class CosmoShopNewsletterRecipientImportTest extends AbstractCosmoShopImportExportTestCase
{
    public function testTheDefaultProfileImportsEveryConsentStateAndRepeatsWithoutDuplicates(): void
    {
        $context = Context::createDefaultContext();
        $market = Market::Germany;
        $this->ensureMarketSalesChannel($market, $context);
        $profileId = $this->defaultNewsletterProfileId($context);
        $recipients = [
            'optin' => 'optIn',
            'direct' => 'direct',
            'optout' => 'optOut',
            'notset' => 'notSet',
        ];
        $ids = array_map(
            static fn (string $name): string => CosmoShopCustomerIdentity::newsletterRecipientId($market, $name.'@example.test'),
            array_keys($recipients),
        );
        $mailFlowEvents = [];
        $listener = static function (object $event) use (&$mailFlowEvents): void {
            $mailFlowEvents[] = $event::class;
        };
        $dispatcher = static::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        foreach ([NewsletterRegisterEvent::class, NewsletterConfirmEvent::class, NewsletterUnsubscribeEvent::class] as $eventClass) {
            $dispatcher->addListener($eventClass, $listener);
        }

        try {
            $csv = $this->newsletterCsv($market, $recipients);
            $dryRun = $this->dryRun($profileId, $csv);
            self::assertSame(Progress::STATE_SUCCEEDED, $dryRun->getState(), $this->importResult($dryRun));

            $first = $this->import($profileId, $csv);
            $second = $this->import($profileId, $csv);
            self::assertSame(Progress::STATE_SUCCEEDED, $first->getState(), $this->importResult($first));
            self::assertSame(Progress::STATE_SUCCEEDED, $second->getState(), $this->importResult($second));

            /** @var EntityRepository<NewsletterRecipientCollection> $repository */
            $repository = static::getContainer()->get('newsletter_recipient.repository');
            $stored = $repository->search(new Criteria(array_values($ids)), $context);
            self::assertCount(4, $stored);

            foreach ($recipients as $name => $expectedStatus) {
                $recipient = $stored->get(CosmoShopCustomerIdentity::newsletterRecipientId($market, $name.'@example.test'));
                self::assertInstanceOf(NewsletterRecipientEntity::class, $recipient);
                self::assertSame($expectedStatus, $recipient->getStatus());
                self::assertSame($market->salesChannelId(), $recipient->getSalesChannelId());
                self::assertSame(hash('sha256', $name), $recipient->getHash());
            }

            self::assertSame(4, $repository->search(new Criteria(array_values($ids)), $context)->getTotal());
            self::assertSame([], $mailFlowEvents, 'A migration import must not trigger newsletter mail/Flow events.');
        } finally {
            foreach ([NewsletterRegisterEvent::class, NewsletterConfirmEvent::class, NewsletterUnsubscribeEvent::class] as $eventClass) {
                $dispatcher->removeListener($eventClass, $listener);
            }
            /** @var EntityRepository<NewsletterRecipientCollection> $repository */
            $repository = static::getContainer()->get('newsletter_recipient.repository');
            $existingIds = $repository->searchIds(new Criteria(array_values($ids)), $context)->getIds();
            if ([] !== $existingIds) {
                $repository->delete(array_map(static fn (string $id): array => ['id' => $id], $existingIds), $context);
            }
        }
    }

    private function defaultNewsletterProfileId(Context $context): string
    {
        /** @var EntityRepository<EntityCollection<ImportExportProfileEntity>> $repository */
        $repository = static::getContainer()->get('import_export_profile.repository');
        $profile = $repository->search(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', 'default_newsletter_recipient')),
            $context,
        )->first();
        self::assertInstanceOf(ImportExportProfileEntity::class, $profile);
        self::assertTrue($profile->getSystemDefault());
        self::assertSame('newsletter_recipient', $profile->getSourceEntity());

        return $profile->getId();
    }

    /** @param array<string, string> $recipients */
    private function newsletterCsv(Market $market, array $recipients): string
    {
        $stream = fopen('php://temp', 'w+');
        self::assertIsResource($stream);
        fputcsv($stream, [
            'id', 'email', 'title', 'salutation', 'first_name', 'last_name', 'zip_code', 'city', 'street',
            'status', 'hash', 'sales_channel_id',
        ], ';', '"', '\\', "\n");

        foreach ($recipients as $name => $status) {
            fputcsv($stream, [
                CosmoShopCustomerIdentity::newsletterRecipientId($market, $name.'@example.test'),
                $name.'@example.test',
                '',
                'not_specified',
                'Newsletter',
                ucfirst($name),
                '10115',
                'Berlin',
                'Test street 1',
                $status,
                hash('sha256', $name),
                $market->salesChannelId(),
            ], ';', '"', '\\', "\n");
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        self::assertIsString($csv);

        return rtrim($csv, "\n");
    }
}
