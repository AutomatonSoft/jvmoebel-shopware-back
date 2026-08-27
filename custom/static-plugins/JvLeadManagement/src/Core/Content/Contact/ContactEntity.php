<?php declare(strict_types=1);

namespace Jv\LeadManagement\Core\Content\Contact;

use Jv\LeadManagement\Core\Content\Lead\LeadEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class ContactEntity extends Entity
{
    use EntityIdTrait;

    protected string $leadId;
    protected ?string $visitorId = null;
    protected string $salesChannelId;
    protected string $contactChannel;
    protected string $contactType;
    protected ?string $trackingReference = null;
    protected ?string $provider = null;
    protected ?string $providerReference = null;
    protected ?string $productNumber = null;
    protected ?string $name = null;
    protected ?string $email = null;
    protected ?string $phone = null;
    protected ?string $message = null;

    protected ?LeadEntity $lead = null;
    protected ?SalesChannelEntity $salesChannel = null;

    public function getLeadId(): string
    {
        return $this->leadId;
    }

    public function setLeadId(string $leadId): void
    {
        $this->leadId = $leadId;
    }

    public function getVisitorId(): ?string
    {
        return $this->visitorId;
    }

    public function setVisitorId(?string $visitorId): void
    {
        $this->visitorId = $visitorId;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getContactChannel(): string
    {
        return $this->contactChannel;
    }

    public function setContactChannel(string $contactChannel): void
    {
        $this->contactChannel = $contactChannel;
    }

    public function getContactType(): string
    {
        return $this->contactType;
    }

    public function setContactType(string $contactType): void
    {
        $this->contactType = $contactType;
    }

    public function getTrackingReference(): ?string
    {
        return $this->trackingReference;
    }

    public function setTrackingReference(?string $trackingReference): void
    {
        $this->trackingReference = $trackingReference;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(?string $provider): void
    {
        $this->provider = $provider;
    }

    public function getProviderReference(): ?string
    {
        return $this->providerReference;
    }

    public function setProviderReference(?string $providerReference): void
    {
        $this->providerReference = $providerReference;
    }

    public function getProductNumber(): ?string
    {
        return $this->productNumber;
    }

    public function setProductNumber(?string $productNumber): void
    {
        $this->productNumber = $productNumber;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }

    public function getLead(): ?LeadEntity
    {
        return $this->lead;
    }

    public function setLead(?LeadEntity $lead): void
    {
        $this->lead = $lead;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }
}
