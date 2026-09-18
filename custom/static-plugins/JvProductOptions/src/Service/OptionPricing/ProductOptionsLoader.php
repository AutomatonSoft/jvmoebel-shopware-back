<?php declare(strict_types=1);

namespace Jv\ProductOptions\Service\OptionPricing;

use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateGroup\OptionTemplateGroupEntity;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValue\OptionTemplateValueEntity;
use Jv\ProductOptions\StoreApi\Struct\ProductOptionGroupStruct;
use Jv\ProductOptions\StoreApi\Struct\ProductOptionsStruct;
use Jv\ProductOptions\StoreApi\Struct\ProductOptionSurchargeStruct;
use Jv\ProductOptions\StoreApi\Struct\ProductOptionValueMediaStruct;
use Jv\ProductOptions\StoreApi\Struct\ProductOptionValueStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final readonly class ProductOptionsLoader
{
    public function __construct(
        private OptionTemplateResolver $templateResolver,
        private OptionValueSurchargeResolver $surchargeResolver,
    ) {
    }

    public function load(string $productId, float $baseUnitPrice, SalesChannelContext $context): ProductOptionsStruct
    {
        $template = $this->templateResolver->resolve($productId, $context->getContext());
        $templateGroups = $template?->getGroups();

        if (null === $template || null === $templateGroups) {
            return new ProductOptionsStruct($productId, $template?->getId(), $baseUnitPrice, []);
        }

        /** @var list<OptionTemplateGroupEntity> $sortedGroups */
        $sortedGroups = $this->sortByPositionThenId($templateGroups->getElements());

        /** @var array<string, list<OptionTemplateValueEntity>> $valuesByGroupId */
        $valuesByGroupId = [];
        /** @var list<OptionTemplateValueEntity> $allValues */
        $allValues = [];
        foreach ($sortedGroups as $group) {
            $groupValues = $group->getValues();
            /** @var list<OptionTemplateValueEntity> $sortedValues */
            $sortedValues = null === $groupValues ? [] : $this->sortByPositionThenId($groupValues->getElements());
            $valuesByGroupId[$group->getId()] = $sortedValues;
            array_push($allValues, ...$sortedValues);
        }

        $surcharges = $this->surchargeResolver->resolve($baseUnitPrice, $allValues, $context);
        $resolvedByValueId = [];
        foreach ($surcharges->values as $resolvedValue) {
            $resolvedByValueId[$resolvedValue->valueId] = $resolvedValue;
        }

        $groups = [];
        foreach ($sortedGroups as $group) {
            $values = [];

            foreach ($valuesByGroupId[$group->getId()] as $value) {
                $resolved = $resolvedByValueId[$value->getId()];

                $media = null;
                if (null !== $value->getMedia()) {
                    $media = new ProductOptionValueMediaStruct(
                        $value->getMedia()->getUrl(),
                        $value->getMedia()->getTranslation('alt'),
                    );
                }

                $values[] = new ProductOptionValueStruct(
                    $value->getId(),
                    $value->getTranslation('name') ?? $value->getName() ?? '',
                    $value->getPosition(),
                    $media,
                    $value->getColorHex(),
                    new ProductOptionSurchargeStruct($resolved->type, $resolved->percentage, $resolved->unitAmount),
                );
            }

            $groups[] = new ProductOptionGroupStruct(
                $group->getId(),
                $group->getTranslation('name') ?? $group->getName() ?? '',
                $group->getPosition(),
                $group->getDefaultValueId(),
                $values,
            );
        }

        return new ProductOptionsStruct($productId, $template->getId(), $baseUnitPrice, $groups);
    }

    /**
     * @template T of OptionTemplateGroupEntity|OptionTemplateValueEntity
     *
     * @param array<string, T> $entities
     *
     * @return list<T>
     */
    private function sortByPositionThenId(array $entities): array
    {
        $sorted = array_values($entities);

        usort($sorted, static function ($a, $b): int {
            $positionComparison = $a->getPosition() <=> $b->getPosition();

            return 0 !== $positionComparison ? $positionComparison : strcmp($a->getId(), $b->getId());
        });

        return $sorted;
    }
}
