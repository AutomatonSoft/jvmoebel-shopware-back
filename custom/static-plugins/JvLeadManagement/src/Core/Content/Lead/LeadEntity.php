<?php declare(strict_types=1);

namespace Jv\LeadManagement\Core\Content\Lead;

use Jv\LeadManagement\Core\Content\Contact\ContactCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class LeadEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $visitorId = null;
    protected string $salesChannelId;
    protected string $domain;
    protected string $marketCode;
    protected string $firstContactChannel;
    protected string $contactType;
    protected ?string $name = null;
    protected ?string $email = null;
    protected ?string $phone = null;
    protected ?string $message = null;
    protected string $status;
    protected ?string $customerId = null;
    protected ?string $orderId = null;

    protected ?SalesChannelEntity $salesChannel = null;
    protected ?CustomerEntity $customer = null;
    protected ?OrderEntity $order = null;
    protected ?ContactCollection $contacts = null;

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

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    public function getMarketCode(): string
    {
        return $this->marketCode;
    }

    public function setMarketCode(string $marketCode): void
    {
        $this->marketCode = $marketCode;
    }

    public function getFirstContactChannel(): string
    {
        return $this->firstContactChannel;
    }

    public function setFirstContactChannel(string $firstContactChannel): void
    {
        $this->firstContactChannel = $firstContactChannel;
    }

    public function getContactType(): string
    {
        return $this->contactType;
    }

    public function setContactType(string $contactType): void
    {
        $this->contactType = $contactType;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function setCustomerId(?string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(?OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getContacts(): ?ContactCollection
    {
        return $this->contacts;
    }

    public function setContacts(?ContactCollection $contacts): void
    {
        $this->contacts = $contacts;
    }
}
