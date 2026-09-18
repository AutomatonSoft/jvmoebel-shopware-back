<?php declare(strict_types=1);

namespace Jv\ProductOptions\StoreApi\Route;

use Jv\ProductOptions\Service\OptionPricing\FixedSurchargeAmountResolver;
use Jv\ProductOptions\Service\OptionPricing\OptionSurchargeCalculator;
use Jv\ProductOptions\Service\OptionPricing\OptionTemplateResolver;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
final class ProductOptionsRoute
{
    /**
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private SalesChannelRepository $productRepository,
        private OptionTemplateResolver $templateResolver,
        private OptionSurchargeCalculator $surchargeCalculator,
        private FixedSurchargeAmountResolver $fixedResolver,
    ) {
    }

    public function getDecorated(): never
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/jv-product-options/{productId}',
        name: 'store-api.jv-product-options',
        methods: ['POST']
    )]
    public function load(string $productId, Request $request, SalesChannelContext $context): JsonResponse
    {
        $criteria = new Criteria([$productId]);
        /** @var SalesChannelProductEntity|null $product */
        $product = $this->productRepository->search($criteria, $context)->get($productId);

        if ($product === null) {
            return new JsonResponse(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        $baseUnitPrice = $product->getCalculatedPrice()->getUnitPrice();

        $template = $this->templateResolver->resolve($productId, $context->getContext());

        if ($template === null) {
            return new JsonResponse([
                'apiAlias' => 'jv_product_options',
                'productId' => $productId,
                'templateId' => null,
                'baseUnitPrice' => $baseUnitPrice,
                'groups' => [],
            ], Response::HTTP_OK);
        }

        $currencyId = $context->getCurrencyId();
        $currencyFactor = $context->getCurrency()->getFactor();
        $isGross = $context->getCurrentCustomerGroup()->getDisplayGross();
        $cashRounding = $context->getItemRounding();

        $groups = [];
        $templateGroups = $template->getGroups();

        if ($templateGroups !== null) {
            $sortedGroups = $templateGroups->getElements();
            usort(
                $sortedGroups,
                static function ($a, $b): int {
                    $pos = $a->getPosition() <=> $b->getPosition();
                    return $pos !== 0 ? $pos : strcmp($a->getId(), $b->getId());
                }
            );

            foreach ($sortedGroups as $group) {
                $values = [];
                $groupValues = $group->getValues();

                if ($groupValues !== null) {
                    $sortedValues = $groupValues->getElements();
                    usort(
                        $sortedValues,
                        static function ($a, $b): int {
                            $pos = $a->getPosition() <=> $b->getPosition();
                            return $pos !== 0 ? $pos : strcmp($a->getId(), $b->getId());
                        }
                    );

                    foreach ($sortedValues as $value) {
                        $surchargeType = $value->getSurchargeType();
                        $percentage = null;
                        $unitAmount = 0.0;

                        if ($surchargeType === 'fixed') {
                            $rawAmount = $this->fixedResolver->resolve($value->getSurchargePrice(), $currencyId, $currencyFactor, $isGross);
                            $unitAmount = $this->surchargeCalculator->round($rawAmount, $cashRounding);
                        } elseif ($surchargeType === 'percentage') {
                            $percentage = (float) $value->getSurchargePercentage();
                            $rawAmount = $baseUnitPrice * ($percentage / 100.0);
                            $unitAmount = $this->surchargeCalculator->round($rawAmount, $cashRounding);
                        }

                        $media = null;
                        if ($value->getMedia() !== null) {
                            $media = [
                                'id' => $value->getMedia()->getId(),
                                'url' => $value->getMedia()->getUrl(),
                            ];
                        }

                        $values[] = [
                            'id' => $value->getId(),
                            'name' => $value->getTranslation('name') ?? $value->getName() ?? '',
                            'position' => $value->getPosition(),
                            'colorHex' => $value->getColorHex(),
                            'media' => $media,
                            'surcharge' => [
                                'type' => $surchargeType,
                                'percentage' => $percentage,
                                'unitAmount' => $unitAmount,
                            ],
                        ];
                    }
                }

                $groups[] = [
                    'id' => $group->getId(),
                    'name' => $group->getTranslation('name') ?? $group->getName() ?? '',
                    'position' => $group->getPosition(),
                    'defaultValueId' => $group->getDefaultValueId(),
                    'values' => $values,
                ];
            }
        }

        return new JsonResponse([
            'apiAlias' => 'jv_product_options',
            'productId' => $productId,
            'templateId' => $template->getId(),
            'baseUnitPrice' => $baseUnitPrice,
            'groups' => $groups,
        ], Response::HTTP_OK);
    }
}
