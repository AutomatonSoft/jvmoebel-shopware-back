<?php

declare(strict_types=1);

namespace Jv\Cms\StoreApi\Search\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class SuggestMediaStruct extends Struct
{
    public function __construct(
        protected string $url,
        protected string $alt = '',
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getAlt(): string
    {
        return $this->alt;
    }

    public function getApiAlias(): string
    {
        return 'jv_search_suggest_media';
    }
}
