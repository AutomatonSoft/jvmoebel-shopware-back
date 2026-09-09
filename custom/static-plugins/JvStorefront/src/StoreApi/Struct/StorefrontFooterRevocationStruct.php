<?php declare(strict_types=1);

namespace Jv\Storefront\StoreApi\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class StorefrontFooterRevocationStruct extends Struct
{
    public function __construct(
        protected bool $enabled,
        protected ?string $buttonLabel,
        protected ?string $recipientEmail,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getButtonLabel(): ?string
    {
        return $this->buttonLabel;
    }

    public function getRecipientEmail(): ?string
    {
        return $this->recipientEmail;
    }

    public function getApiAlias(): string
    {
        return 'jv_storefront_footer_revocation';
    }
}
