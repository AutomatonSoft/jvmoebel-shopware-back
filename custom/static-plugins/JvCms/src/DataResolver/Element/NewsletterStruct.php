<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

final class NewsletterStruct extends Struct
{
    public function __construct(
        protected string $title = '',
        protected ?string $eyebrow = null,
        protected string $description = '',
        protected string $buttonLabel = '',
        protected string $buttonSize = 'medium',
        protected string $placeholder = '',
        protected ?string $storefrontUrl = null,
        protected string $successMessage = '',
        protected string $invalidEmailMessage = '',
        protected string $errorMessage = '',
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getEyebrow(): ?string
    {
        return $this->eyebrow;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getButtonLabel(): string
    {
        return $this->buttonLabel;
    }

    public function getButtonSize(): string
    {
        return $this->buttonSize;
    }

    public function getPlaceholder(): string
    {
        return $this->placeholder;
    }

    public function getStorefrontUrl(): ?string
    {
        return $this->storefrontUrl;
    }

    public function getSuccessMessage(): string
    {
        return $this->successMessage;
    }

    public function getInvalidEmailMessage(): string
    {
        return $this->invalidEmailMessage;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_newsletter';
    }
}
