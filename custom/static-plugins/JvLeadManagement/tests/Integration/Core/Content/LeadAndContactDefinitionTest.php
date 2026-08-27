<?php declare(strict_types=1);

namespace Jv\LeadManagement\Tests\Integration\Core\Content;

use Jv\LeadManagement\Core\Content\Contact\ContactCollection;
use Jv\LeadManagement\Core\Content\Contact\ContactEntity;
use Jv\LeadManagement\Core\Content\Lead\LeadCollection;
use Jv\LeadManagement\Core\Content\Lead\LeadEntity;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

final class LeadAndContactDefinitionTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItPersistsLeadWithContactAssociation(): void
    {
        $context = Context::createDefaultContext();

        /** @var EntityRepository<LeadCollection> $leadRepository */
        $leadRepository = static::getContainer()->get(
            'jv_lead_management_lead.repository',
        );

        $salesChannelId = $this->salesChannelId();
        $leadId = Uuid::randomHex();
        $contactId = Uuid::randomHex();

        $leadRepository->create([[
            'id' => $leadId,
            'visitorId' => '550e8400-e29b-41d4-a716-446655440000',
            'salesChannelId' => $salesChannelId,
            'domain' => 'jvmoebel.de',
            'marketCode' => 'de',
            'firstContactChannel' => 'form',
            'contactType' => 'offer_request',
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'message' => 'Please send an offer',
            'status' => 'new',
            'contacts' => [[
                'id' => $contactId,
                'visitorId' => '550e8400-e29b-41d4-a716-446655440000',
                'salesChannelId' => $salesChannelId,
                'contactChannel' => 'form',
                'contactType' => 'offer_request',
                'trackingReference' => 'tracking-test',
                'productNumber' => 'SOFA-001',
                'email' => 'test@example.com',
                'message' => 'Please send an offer',
            ]],
        ]], $context);

        $lead = $leadRepository->search(
            (new Criteria([$leadId]))
                ->addAssociation('contacts'),
            $context,
        )->first();

        self::assertInstanceOf(LeadEntity::class, $lead);
        self::assertSame('new', $lead->getStatus());
        self::assertSame($salesChannelId, $lead->getSalesChannelId());

        $contacts = $lead->getContacts();

        self::assertInstanceOf(ContactCollection::class, $contacts);
        self::assertCount(1, $contacts);

        $contact = $contacts->first();

        self::assertInstanceOf(ContactEntity::class, $contact);
        self::assertSame($contactId, $contact->getId());
        self::assertSame($leadId, $contact->getLeadId());
        self::assertSame('form', $contact->getContactChannel());
    }

    private function salesChannelId(): string
    {
        /** @var EntityRepository $salesChannelRepository */
        $salesChannelRepository = static::getContainer()
            ->get('sales_channel.repository');

        $id = $salesChannelRepository
            ->searchIds(new Criteria(), Context::createDefaultContext())
            ->firstId();

        self::assertNotNull($id);

        return $id;
    }
}
