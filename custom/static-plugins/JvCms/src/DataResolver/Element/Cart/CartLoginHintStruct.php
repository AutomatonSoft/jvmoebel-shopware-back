<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element\Cart;

use Shopware\Core\Framework\Struct\Struct;

final class CartLoginHintStruct extends Struct
{
    public function __construct(
        protected string $message,
        protected string $loginLabel,
        protected string $loginUrl,
    ) {
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getLoginLabel(): string
    {
        return $this->loginLabel;
    }

    public function getLoginUrl(): string
    {
        return $this->loginUrl;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_cart_login_hint';
    }
}
