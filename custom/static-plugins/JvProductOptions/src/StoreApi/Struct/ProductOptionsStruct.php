<?php declare(strict_types=1);

namespace Jv\ProductOptions\StoreApi\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class ProductOptionsStruct extends Struct
{
    /**
     * @param list<ProductOptionGroupStruct> $groups
     */
    public function __construct(
        protected string $productId,
        protected ?string $templateId,
        protected float $baseUnitPrice,
        protected array $groups,
    ) {
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getTemplateId(): ?string
    {
        return $this->templateId;
    }

    public function getBaseUnitPrice(): float
    {
        return $this->baseUnitPrice;
    }

    /**
     * @return list<ProductOptionGroupStruct>
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    public function getApiAlias(): string
    {
        return 'jv_product_options';
    }
}
