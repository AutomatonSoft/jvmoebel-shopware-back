<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Shopware\Core\Framework\Struct\Struct;

/** Store API `data` for `jv-expert-quote`. */
final class ExpertQuoteStruct extends Struct
{
    public function __construct(
        protected string $quote = '',
        protected string $authorName = '',
        protected string $authorRole = '',
    ) {
    }

    public function getQuote(): string
    {
        return $this->quote;
    }

    public function getAuthorName(): string
    {
        return $this->authorName;
    }

    public function getAuthorRole(): string
    {
        return $this->authorRole;
    }

    public function getApiAlias(): string
    {
        return 'cms_jv_expert_quote';
    }
}
