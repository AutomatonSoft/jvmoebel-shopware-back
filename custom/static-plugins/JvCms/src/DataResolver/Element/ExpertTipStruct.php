<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-expert-tip`. */
final class ExpertTipStruct extends Struct
{
    public function __construct(
        protected string $label = 'Tipp',
        protected string $title = '',
        protected string $body = '',
    ) {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_expert_tip';
    }
}
