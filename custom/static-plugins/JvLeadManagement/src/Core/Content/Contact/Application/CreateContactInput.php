<?php declare(strict_types=1);

namespace Jv\LeadManagement\Core\Content\Contact\Application;

final readonly class CreateContactInput
{
    public function __construct(
        public string $salesChannelId,
        public string $domain,
        public string $marketCode,
        public string $contactChannel,
        public string $contactType,
        public ?string $visitorId = null,
        public ?string $leadId = null,
        public ?string $trackingReference = null,
        public ?string $productNumber = null,
        public ?string $provider = null,
        public ?string $providerReference = null,
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $message = null,
    ) {
    }
}
