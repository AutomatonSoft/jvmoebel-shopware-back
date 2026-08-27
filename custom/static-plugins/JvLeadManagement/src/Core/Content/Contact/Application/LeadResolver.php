<?php declare(strict_types=1);

namespace Jv\LeadManagement\Core\Content\Contact\Application;

use Jv\LeadManagement\Core\Content\Lead\LeadEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

final class LeadResolver
{
    public function __construct(
        private readonly EntityRepository $leadRepository,
    ) {
    }

    public function resolve(
        CreateContactInput $input,
        Context $context,
    ): ?LeadEntity {
        if ($input->leadId !== null) {
            return $this->findById($input->leadId, $context);
        }

        if ($input->trackingReference !== null) {
            $lead = $this->findByTrackingReference(
                $input->trackingReference,
                $context,
            );

            if ($lead !== null) {
                return $lead;
            }
        }

        if ($input->visitorId === null) {
            return null;
        }

        return $this->findOpenLead(
            $input->visitorId,
            $input->salesChannelId,
            $input->productNumber,
            $context,
        );
    }

    private function findById(
        string $leadId,
        Context $context,
    ): ?LeadEntity {
        return $this->leadRepository
            ->search(new Criteria([$leadId]), $context)
            ->first();
    }

    private function findByTrackingReference(
        string $trackingReference,
        Context $context,
    ): ?LeadEntity {
        $criteria = (new Criteria())
            ->addAssociation('contacts')
            ->addFilter(new EqualsFilter(
                'contacts.trackingReference',
                $trackingReference,
            ))
            ->setLimit(2);

        $result = $this->leadRepository->search($criteria, $context);

        if ($result->getTotal() !== 1) {
            return null;
        }

        return $result->first();
    }

    private function findOpenLead(
        string $visitorId,
        string $salesChannelId,
        ?string $productNumber,
        Context $context,
    ): ?LeadEntity {
        $criteria = (new Criteria())
            ->addAssociation('contacts')
            ->addFilter(new EqualsFilter('visitorId', $visitorId))
            ->addFilter(new EqualsFilter(
                'salesChannelId',
                $salesChannelId,
            ))
            ->addFilter(new EqualsFilter(
                'contacts.productNumber',
                $productNumber,
            ))
            ->addFilter(new EqualsAnyFilter('status', [
                'new',
                'contacted',
                'offer_sent',
            ]))
            ->setLimit(2);

        $result = $this->leadRepository->search($criteria, $context);

        if ($result->getTotal() !== 1) {
            return null;
        }

        return $result->first();
    }
}
