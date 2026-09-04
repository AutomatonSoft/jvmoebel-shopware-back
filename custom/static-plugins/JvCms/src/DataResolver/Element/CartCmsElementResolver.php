<?php

declare(strict_types=1);

namespace Jv\Cms\DataResolver\Element;

use Jv\Cms\DataResolver\Element\Cart\CartActionLinkStruct;
use Jv\Cms\DataResolver\Element\Cart\CartDeliveryStruct;
use Jv\Cms\DataResolver\Element\Cart\CartFormInputStruct;
use Jv\Cms\DataResolver\Element\Cart\CartFormSectionStruct;
use Jv\Cms\DataResolver\Element\Cart\CartFormSubmitStruct;
use Jv\Cms\DataResolver\Element\Cart\CartLineItemStruct;
use Jv\Cms\DataResolver\Element\Cart\CartLoginHintStruct;
use Jv\Cms\DataResolver\Element\Cart\CartPostalCodeStruct;
use Jv\Cms\DataResolver\Element\Cart\CartProductStruct;
use Jv\Cms\DataResolver\Element\Cart\CartSavingsStruct;
use Jv\Cms\DataResolver\Element\Cart\CartServiceOptionStruct;
use Jv\Cms\DataResolver\Element\Cart\CartServicesStruct;
use Jv\Cms\DataResolver\Element\Cart\CartSummaryStruct;
use Jv\Cms\DataResolver\Element\Cart\CartTrustItemStruct;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Resolves CMS element `jv-cart` for the Store API (platform SPEC-010 / backend SPEC-012).
 *
 * Persisted config is untrusted. Invalid product/media UUIDs never reach Criteria.
 * Bad cart state or malformed config must not HTTP 500.
 */
final class CartCmsElementResolver extends AbstractCmsElementResolver
{
    public const string TYPE = 'jv-cart';

    private const string DEFAULT_HEADER_LABEL = 'Warenkorb';

    private const string DEFAULT_CART_URL = '/cart';

    private const string DEFAULT_LOGIN_URL = '/login';

    public function __construct(
        private readonly CartService $cartService,
    ) {
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $cart = $this->loadCart($resolverContext->getSalesChannelContext());
        $productIds = $this->productIdsFromCart($cart);

        if ([] === $productIds) {
            return null;
        }

        $criteria = new Criteria(array_values($productIds));
        $criteria->addAssociation('cover.media');
        $criteria->addAssociation('seoUrls');

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add(
            $this->productCriteriaKey($slot),
            ProductDefinition::class,
            $criteria,
        );

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $salesChannelContext = $resolverContext->getSalesChannelContext();
        $cart = $this->loadCart($salesChannelContext);
        $products = $this->productMap($result->get($this->productCriteriaKey($slot)));

        $servicesTemplate = $this->normalizeServicesTemplate($config);
        $lineItems = $this->normalizeLineItems($cart, $products, $servicesTemplate, $salesChannelContext);
        $itemCount = $this->sumItemCount($lineItems);

        $headerConfig = $this->objectValue($config->get('headerTrigger')?->getValue());
        $headerLabel = $this->requiredString($headerConfig['label'] ?? null);
        if ('' === $headerLabel) {
            $headerLabel = self::DEFAULT_HEADER_LABEL;
        }

        $headerUrl = $this->safeCartHref(\is_string($headerConfig['url'] ?? null) ? $headerConfig['url'] : null)
            ?? self::DEFAULT_CART_URL;

        $summaryLabels = $this->objectValue($config->get('summaryLabels')?->getValue());

        $slot->setData(new CartStruct(
            locale: $salesChannelContext->getLanguageInfo()->localeCode,
            currency: $salesChannelContext->getCurrency()->getIsoCode(),
            headerTrigger: new CartHeaderTriggerStruct(
                label: $headerLabel,
                url: $headerUrl,
                itemCount: $itemCount,
            ),
            title: $this->resolveCountTitle(
                $this->requiredString($config->get('titleSingular')?->getValue()),
                $this->requiredString($config->get('titlePlural')?->getValue()),
                \count($lineItems),
            ),
            loginHint: $this->normalizeLoginHint($config),
            lineItems: $lineItems,
            summary: $this->normalizeSummary($summaryLabels, $lineItems, $config, \count($lineItems)),
        ));
    }

    private function loadCart(SalesChannelContext $context): Cart
    {
        return $this->cartService->getCart($context->getToken(), $context);
    }

    /**
     * @return array<string, string>
     */
    private function productIdsFromCart(Cart $cart): array
    {
        $ids = [];
        foreach ($cart->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE) as $lineItem) {
            $id = $this->normalizeUuid($lineItem->getReferencedId());
            if (null !== $id) {
                $ids[$id] = $id;
            }
        }

        return $ids;
    }

    private function productCriteriaKey(CmsSlotEntity $slot): string
    {
        return 'jv_cart_product_'.$slot->getUniqueIdentifier();
    }

    /**
     * @param EntitySearchResult<covariant EntityCollection<covariant Entity>>|null $searchResult
     *
     * @return array<string, ProductEntity>
     */
    private function productMap(?EntitySearchResult $searchResult): array
    {
        if (null === $searchResult) {
            return [];
        }

        $map = [];
        foreach ($searchResult->getEntities() as $entity) {
            if (!$entity instanceof ProductEntity) {
                continue;
            }

            $map[$entity->getUniqueIdentifier()] = $entity;
        }

        return $map;
    }

    /**
     * @param array<string, ProductEntity> $products
     *
     * @return list<CartLineItemStruct>
     */
    private function normalizeLineItems(
        Cart $cart,
        array $products,
        ?CartServicesStruct $servicesTemplate,
        SalesChannelContext $context,
    ): array {
        $normalized = [];

        foreach ($cart->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE) as $lineItem) {
            $mapped = $this->normalizeLineItem($lineItem, $products, $servicesTemplate, $context);
            if (null !== $mapped) {
                $normalized[] = $mapped;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, ProductEntity> $products
     */
    private function normalizeLineItem(
        LineItem $lineItem,
        array $products,
        ?CartServicesStruct $servicesTemplate,
        SalesChannelContext $context,
    ): ?CartLineItemStruct {
        $quantity = $lineItem->getQuantity();
        if ($quantity < 1) {
            return null;
        }

        $productId = $this->normalizeUuid($lineItem->getReferencedId());
        if (null === $productId) {
            return null;
        }

        $productEntity = $products[$productId] ?? null;
        if (!$productEntity instanceof ProductEntity) {
            return null;
        }

        $name = $this->requiredString($productEntity->getTranslation('name') ?? $lineItem->getLabel());
        if ('' === $name) {
            return null;
        }

        $url = $this->resolveProductUrl($productEntity, $context);
        if (null === $url) {
            return null;
        }

        $image = $this->resolveImage($productEntity, $lineItem, $name);
        if (null === $image) {
            return null;
        }

        $price = $this->normalizePrice($lineItem->getPrice());
        if (null === $price) {
            return null;
        }

        $description = $this->optionalString($productEntity->getTranslation('description') ?? $lineItem->getDescription());

        $payload = $lineItem->getPayload();
        $seller = $this->optionalString($payload['seller'] ?? null);

        return new CartLineItemStruct(
            id: $lineItem->getId(),
            seller: $seller,
            quantity: $quantity,
            product: new CartProductStruct(
                name: $name,
                description: $description,
                url: $url,
                image: $image,
            ),
            delivery: $this->normalizeDelivery($payload),
            price: $price,
            services: $servicesTemplate,
        );
    }

    private function normalizePrice(?CalculatedPrice $calculatedPrice): ?CartPriceStruct
    {
        if (null === $calculatedPrice) {
            return null;
        }

        $unitPrice = $calculatedPrice->getUnitPrice();
        if (!is_finite($unitPrice) || $unitPrice < 0) {
            return null;
        }

        $uvp = null;
        $discountPercent = null;
        $listPrice = $calculatedPrice->getListPrice();
        if (null !== $listPrice) {
            $list = $listPrice->getPrice();
            if (is_finite($list) && $list > $unitPrice) {
                $uvp = $list;
                $discountPercent = max(0, min(100, (int) round((($uvp - $unitPrice) / $uvp) * 100)));
            }
        }

        return new CartPriceStruct($unitPrice, $uvp, $discountPercent);
    }

    private function resolveProductUrl(ProductEntity $product, SalesChannelContext $context): ?string
    {
        $salesChannelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();
        $fallback = null;

        foreach ($product->getSeoUrls() ?? [] as $seoUrl) {
            if ($seoUrl->getSalesChannelId() !== $salesChannelId || $seoUrl->getLanguageId() !== $languageId) {
                continue;
            }

            $href = $this->seoPathToHref($seoUrl->getSeoPathInfo());
            if (null === $href) {
                continue;
            }

            if ($seoUrl->getIsCanonical()) {
                return $href;
            }

            $fallback ??= $href;
        }

        return $fallback;
    }

    private function seoPathToHref(?string $seoPathInfo): ?string
    {
        $path = trim((string) $seoPathInfo);
        if ('' === $path) {
            return null;
        }

        return $this->safeCartHref('/'.ltrim($path, '/'));
    }

    private function resolveImage(ProductEntity $product, LineItem $lineItem, string $name): ?CartMediaStruct
    {
        $cover = $product->getCover()?->getMedia();
        if ($cover instanceof MediaEntity) {
            $mapped = $this->mediaStruct($cover, $name);
            if (null !== $mapped) {
                return $mapped;
            }
        }

        $lineCover = $lineItem->getCover();
        if ($lineCover instanceof MediaEntity) {
            return $this->mediaStruct($lineCover, $name);
        }

        return null;
    }

    private function mediaStruct(MediaEntity $media, string $fallbackAlt): ?CartMediaStruct
    {
        $url = $media->getUrl();
        if ('' === $url) {
            return null;
        }

        $alt = trim((string) ($media->getTranslated()['alt'] ?? $fallbackAlt));

        return new CartMediaStruct($url, $alt);
    }

    private function normalizeDelivery(mixed $payload): ?CartDeliveryStruct
    {
        if (!\is_array($payload)) {
            return null;
        }

        $estimate = '';
        $method = '';

        if (isset($payload['delivery']) && \is_array($payload['delivery'])) {
            $estimate = $this->requiredString($payload['delivery']['estimate'] ?? null);
            $method = $this->requiredString($payload['delivery']['method'] ?? null);
        }

        $estimate = '' !== $estimate ? $estimate : $this->requiredString($payload['deliveryEstimate'] ?? null);
        $method = '' !== $method ? $method : $this->requiredString($payload['deliveryMethod'] ?? null);

        if ('' === $estimate && '' === $method) {
            return null;
        }

        return new CartDeliveryStruct($estimate, $method);
    }

    private function normalizeLoginHint(FieldConfigCollection $config): ?CartLoginHintStruct
    {
        $value = $this->objectValue($config->get('loginHint')?->getValue());
        $message = $this->requiredString($value['message'] ?? null);
        if ('' === $message) {
            return null;
        }

        $loginUrl = $this->safeCartHref(\is_string($value['loginUrl'] ?? null) ? $value['loginUrl'] : null)
            ?? self::DEFAULT_LOGIN_URL;

        return new CartLoginHintStruct(
            message: $message,
            loginLabel: $this->requiredString($value['loginLabel'] ?? null),
            loginUrl: $loginUrl,
        );
    }

    private function normalizeServicesTemplate(FieldConfigCollection $config): ?CartServicesStruct
    {
        $value = $this->objectValue($config->get('services')?->getValue());
        $title = $this->requiredString($value['title'] ?? null);
        $postalCodeValue = isset($value['postalCode']) && \is_array($value['postalCode']) ? $value['postalCode'] : [];

        $options = $this->normalizeServiceOptions($value['options'] ?? []);
        if ('' === $title && [] === $options) {
            return null;
        }

        return new CartServicesStruct(
            title: $title,
            postalCode: new CartPostalCodeStruct(
                label: $this->requiredString($postalCodeValue['label'] ?? null),
                placeholder: $this->requiredString($postalCodeValue['placeholder'] ?? null),
                submitLabel: $this->requiredString($postalCodeValue['submitLabel'] ?? null),
            ),
            options: $options,
        );
    }

    /**
     * @return list<CartServiceOptionStruct>
     */
    private function normalizeServiceOptions(mixed $value): array
    {
        $entries = $this->listEntries($value);
        $normalized = [];
        $seenIds = [];

        foreach ($entries as $entry) {
            $id = $this->requiredString($entry['id'] ?? null);
            $label = $this->requiredString($entry['label'] ?? null);
            if ('' === $id || '' === $label || isset($seenIds[$id])) {
                continue;
            }

            $seenIds[$id] = true;
            $price = $entry['price'] ?? 0;
            if (!\is_int($price) && !\is_float($price)) {
                $price = 0.0;
            }
            $price = (float) $price;
            if (!is_finite($price) || $price < 0) {
                $price = 0.0;
            }

            $normalized[] = new CartServiceOptionStruct($id, $label, $price);
        }

        return $normalized;
    }

    /**
     * @param list<CartLineItemStruct> $lineItems
     * @param array<string, mixed>     $summaryLabels
     */
    private function normalizeSummary(
        array $summaryLabels,
        array $lineItems,
        FieldConfigCollection $config,
        int $lineItemCount,
    ): CartSummaryStruct {
        $subtotal = 0.0;
        $savingsAmount = 0.0;

        foreach ($lineItems as $lineItem) {
            $price = $lineItem->getPrice();
            $subtotal += $price->getUnitPrice() * $lineItem->getQuantity();

            $uvp = $price->getUvp();
            if (null !== $uvp && $uvp > $price->getUnitPrice()) {
                $savingsAmount += ($uvp - $price->getUnitPrice()) * $lineItem->getQuantity();
            }
        }

        if (!is_finite($subtotal) || $subtotal < 0) {
            $subtotal = 0.0;
        }

        if (!is_finite($savingsAmount) || $savingsAmount <= 0) {
            $savingsAmount = 0.0;
        }

        $savingsLabelTemplate = $this->requiredString($summaryLabels['savingsLabel'] ?? null);
        $savings = null;
        if ($savingsAmount > 0) {
            $savings = new CartSavingsStruct(
                label: $this->interpolateAmount($savingsLabelTemplate, $savingsAmount),
                amount: $savingsAmount,
            );
        }

        $checkoutLabel = $this->requiredString($summaryLabels['checkoutLabel'] ?? null);
        $checkoutUrl = $this->safeCartHref(\is_string($summaryLabels['checkoutUrl'] ?? null) ? $summaryLabels['checkoutUrl'] : null);
        $checkout = null;
        if ('' !== $checkoutLabel && null !== $checkoutUrl) {
            $checkout = new CartActionLinkStruct($checkoutLabel, $checkoutUrl);
        }

        return new CartSummaryStruct(
            title: $this->resolveCountTitle(
                $this->requiredString($summaryLabels['titleSingular'] ?? null),
                $this->requiredString($summaryLabels['titlePlural'] ?? null),
                $lineItemCount,
            ),
            subtotalLabel: $this->requiredString($summaryLabels['subtotalLabel'] ?? null),
            subtotal: $subtotal,
            shippingLabel: $this->requiredString($summaryLabels['shippingLabel'] ?? null),
            shippingUrl: $this->safeCartHref(\is_string($summaryLabels['shippingUrl'] ?? null) ? $summaryLabels['shippingUrl'] : null),
            totalLabel: $this->requiredString($summaryLabels['totalLabel'] ?? null),
            total: $subtotal,
            savings: $savings,
            checkout: $checkout,
            promoCode: $this->normalizeFormSection($config->get('promoCode')?->getValue(), 'promoCode'),
            giftCard: $this->normalizeFormSection($config->get('giftCard')?->getValue(), 'giftCardCode'),
            trust: $this->normalizeTrust($config->get('trust')?->getValue()),
        );
    }

    private function normalizeFormSection(mixed $value, string $defaultInputName): ?CartFormSectionStruct
    {
        $section = $this->objectValue($value);
        $title = $this->requiredString($section['title'] ?? null);
        if ('' === $title) {
            return null;
        }

        $inputName = $this->requiredString($section['inputName'] ?? null);
        if ('' === $inputName) {
            $inputName = $defaultInputName;
        }

        return new CartFormSectionStruct(
            title: $title,
            expanded: $this->normalizeExpanded($section['expanded'] ?? false),
            description: $this->optionalString($section['description'] ?? null),
            input: new CartFormInputStruct(
                name: $inputName,
                label: $this->requiredString($section['inputLabel'] ?? null),
                placeholder: $this->requiredString($section['inputPlaceholder'] ?? null),
            ),
            submit: new CartFormSubmitStruct(
                label: $this->requiredString($section['submitLabel'] ?? null),
            ),
        );
    }

    /**
     * @return list<CartTrustItemStruct>
     */
    private function normalizeTrust(mixed $value): array
    {
        $normalized = [];
        foreach ($this->listEntries($value) as $entry) {
            $label = $this->requiredString($entry['label'] ?? null);
            if ('' === $label) {
                continue;
            }

            $normalized[] = new CartTrustItemStruct($label);
        }

        return $normalized;
    }

    /**
     * @param list<CartLineItemStruct> $lineItems
     */
    private function sumItemCount(array $lineItems): int
    {
        $count = 0;
        foreach ($lineItems as $lineItem) {
            $count += $lineItem->getQuantity();
        }

        return max(0, $count);
    }

    private function resolveCountTitle(string $singular, string $plural, int $count): string
    {
        $template = 1 === $count ? $singular : ('' !== $plural ? $plural : $singular);
        if ('' === $template) {
            return '';
        }

        return str_replace('{count}', (string) $count, $template);
    }

    private function interpolateAmount(string $template, float $amount): string
    {
        if ('' === $template) {
            return (string) $amount;
        }

        return str_replace('{amount}', (string) $amount, $template);
    }

    private function normalizeExpanded(mixed $value): bool
    {
        return true === $value || 1 === $value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listEntries(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        if (!array_is_list($value)) {
            $value = array_values($value);
        }

        $entries = [];
        foreach ($value as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $entries[] = $item;
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private function objectValue(mixed $value): array
    {
        return \is_array($value) ? $value : [];
    }

    /**
     * CMS config is untrusted persisted input. Only valid Shopware UUIDs reach DAL.
     */
    private function normalizeUuid(mixed $value): ?string
    {
        if (!\is_string($value) && !\is_int($value)) {
            return null;
        }

        $id = strtolower(trim((string) $value));
        if ('' === $id || !Uuid::isValid($id)) {
            return null;
        }

        return $id;
    }

    private function requiredString(mixed $value): string
    {
        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            return '';
        }

        return trim((string) $value);
    }

    private function optionalString(mixed $value): ?string
    {
        $string = $this->requiredString($value);

        return '' === $string ? null : $string;
    }

    /**
     * Root-relative `/path` (not `//…`) or absolute http(s) with a host.
     */
    private function safeCartHref(?string $href): ?string
    {
        $href = trim((string) $href);
        if ('' === $href) {
            return null;
        }

        if (str_starts_with($href, '/') && !str_starts_with($href, '//')) {
            return $href;
        }

        if (false === filter_var($href, \FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($href);
        if (!\is_array($parts)) {
            return null;
        }

        $schemeRaw = $parts['scheme'] ?? null;
        $scheme = \is_string($schemeRaw) ? strtolower($schemeRaw) : '';
        if (!\in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = $parts['host'] ?? null;
        if (!\is_string($host) || '' === $host) {
            return null;
        }

        return $href;
    }
}
