<?php declare(strict_types=1);

namespace Jv\Storefront\StoreApi\Struct;

use Jv\Storefront\Core\Content\StorefrontContactChannel\StorefrontContactChannelType;
use Shopware\Core\Framework\Struct\Struct;

final class StorefrontContactChannelStruct extends Struct
{
    /** Serialized as the backed enum value so the Store API payload stays a plain string. */
    protected string $type;

    public function __construct(
        protected string $id,
        StorefrontContactChannelType $type,
        protected string $url,
        protected ?string $label,
        protected int $position,
        protected ?StorefrontMediaStruct $icon,
    ) {
        $this->type = $type->value;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): StorefrontContactChannelType
    {
        return StorefrontContactChannelType::fromStoredValue($this->type);
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getIcon(): ?StorefrontMediaStruct
    {
        return $this->icon;
    }

    /**
     * Must not equal the DAL entity name `jv_storefront_contact_channel`: StructEncoder would then
     * apply the entity field protections (ApiAware for admin only) and strip every property.
     */
    public function getApiAlias(): string
    {
        return 'jv_storefront_contact_widget_channel';
    }
}
