<?php declare(strict_types=1);

namespace Jv\ProductOptions\StoreApi\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class ProductOptionGroupStruct extends Struct
{
    /**
     * @param list<ProductOptionValueStruct> $values
     */
    public function __construct(
        protected string $id,
        protected string $name,
        protected int $position,
        protected ?string $defaultValueId,
        protected array $values,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getDefaultValueId(): ?string
    {
        return $this->defaultValueId;
    }

    /**
     * @return list<ProductOptionValueStruct>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public function getApiAlias(): string
    {
        return 'jv_product_option_group';
    }
}
