<?php declare(strict_types=1);

namespace Jv\LeadManagement\Core\Content\Contact\Application;

use Jv\LeadManagement\Enum\LeadStatus;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

final class CreateContactAction
{
    public function __construct(
        private readonly LeadResolver $leadResolver,
        private readonly EntityRepository $leadRepository,
        private readonly EntityRepository $contactRepository,
    ) {
    }

    public function execute(
        CreateContactInput $input,
        Context $context,
    ): string {
        $lead = $this->leadResolver->resolve($input, $context);

        $leadId = $lead?->getId();

        if ($leadId === null) {
            $leadId = Uuid::randomHex();

            $this->leadRepository->create([[
                'id' => $leadId,
                'visitorId' => $input->visitorId,
                'salesChannelId' => $input->salesChannelId,
                'domain' => $input->domain,
                'marketCode' => $input->marketCode,
                'firstContactChannel' => $input->contactChannel,
                'contactType' => $input->contactType,
                'name' => $input->name,
                'email' => $input->email,
                'phone' => $input->phone,
                'message' => $input->message,
                'status' => LeadStatus::New->value,
            ]], $context);
        }

        $contactId = Uuid::randomHex();

        $this->contactRepository->create([[
            'id' => $contactId,
            'leadId' => $leadId,
            'visitorId' => $input->visitorId,
            'salesChannelId' => $input->salesChannelId,
            'contactChannel' => $input->contactChannel,
            'contactType' => $input->contactType,
            'trackingReference' => $input->trackingReference,
            'provider' => $input->provider,
            'providerReference' => $input->providerReference,
            'productNumber' => $input->productNumber,
            'name' => $input->name,
            'email' => $input->email,
            'phone' => $input->phone,
            'message' => $input->message,
        ]], $context);

        return $contactId;
    }
}