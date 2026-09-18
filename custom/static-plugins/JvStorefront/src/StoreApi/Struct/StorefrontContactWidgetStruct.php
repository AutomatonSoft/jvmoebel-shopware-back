<?php declare(strict_types=1);

namespace Jv\Storefront\StoreApi\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class StorefrontContactWidgetStruct extends Struct
{
    /**
     * @param list<StorefrontContactChannelStruct> $channels
     */
    public function __construct(
        protected array $channels,
    ) {
    }

    /** @return list<StorefrontContactChannelStruct> */
    public function getChannels(): array
    {
        return $this->channels;
    }

    public function getApiAlias(): string
    {
        return 'jv_storefront_contact_widget';
    }
}
