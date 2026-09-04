<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element\Cart;

use Shopware\Core\Framework\Struct\Struct;

final class CartFormSectionStruct extends Struct
{
    public function __construct(
        protected string $title,
        protected bool $expanded,
        protected ?string $description,
        protected CartFormInputStruct $input,
        protected CartFormSubmitStruct $submit,
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function isExpanded(): bool
    {
        return $this->expanded;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getInput(): CartFormInputStruct
    {
        return $this->input;
    }

    public function getSubmit(): CartFormSubmitStruct
    {
        return $this->submit;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_cart_form_section';
    }
}
