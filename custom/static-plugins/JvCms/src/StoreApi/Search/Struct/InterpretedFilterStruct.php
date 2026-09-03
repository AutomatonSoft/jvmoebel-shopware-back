<?php

declare(strict_types=1);

namespace Jv\Cms\StoreApi\Search\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class InterpretedFilterStruct extends Struct
{
    public function __construct(
        protected string $propertyGroupId,
        protected string $propertyGroupName,
        protected string $optionId,
        protected string $optionName,
        protected string $matchedToken,
    ) {
    }

    public function getPropertyGroupId(): string
    {
        return $this->propertyGroupId;
    }

    public function getPropertyGroupName(): string
    {
        return $this->propertyGroupName;
    }

    public function getOptionId(): string
    {
        return $this->optionId;
    }

    public function getOptionName(): string
    {
        return $this->optionName;
    }

    public function getMatchedToken(): string
    {
        return $this->matchedToken;
    }

    public function getApiAlias(): string
    {
        return 'jv_search_interpreted_filter';
    }
}
