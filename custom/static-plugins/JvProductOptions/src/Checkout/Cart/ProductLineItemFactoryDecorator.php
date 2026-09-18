<?php declare(strict_types=1);

namespace Jv\ProductOptions\Checkout\Cart;

use Jv\ProductOptions\Service\OptionPricing\Exception\InvalidOptionSelectionException;
use Jv\ProductOptions\Service\OptionPricing\OptionLineItemIdGenerator;
use Jv\ProductOptions\Service\OptionPricing\OptionSelectionResolver;
use Jv\ProductOptions\Service\OptionPricing\OptionTemplateResolver;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\LineItemFactoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final readonly class ProductLineItemFactoryDecorator implements LineItemFactoryInterface
{
    public function __construct(
        private LineItemFactoryInterface $decorated,
        private OptionTemplateResolver $templateResolver,
        private OptionSelectionResolver $selectionResolver,
        private OptionLineItemIdGenerator $idGenerator,
    ) {
    }

    public function supports(string $type): bool
    {
        return $this->decorated->supports($type);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, SalesChannelContext $context): LineItem
    {
        $productId = (string) ($data['referencedId'] ?? $data['id'] ?? '');

        $hasPayloadSelections = isset($data['payload']['jvOptionSelections']) && is_array($data['payload']['jvOptionSelections']);
        $hasDirectSelections = isset($data['jvOptionSelections']) && is_array($data['jvOptionSelections']);
        $rawSelections = $hasPayloadSelections
            ? $data['payload']['jvOptionSelections']
            : ($hasDirectSelections ? $data['jvOptionSelections'] : null);

        $template = $this->templateResolver->resolve($productId, $context->getContext());
        if (null !== $template) {
            try {
                $effectiveValues = $this->selectionResolver->resolve($template, $rawSelections);
                $effectiveSelections = [];
                foreach ($effectiveValues as $value) {
                    $effectiveSelections[$value->getGroupId()] = $value->getId();
                }
                $data['id'] = $this->idGenerator->generate($productId, $effectiveSelections);
                $data['payload']['jvOptionSelections'] = $effectiveSelections;
            } catch (InvalidOptionSelectionException) {
                if (is_array($rawSelections)) {
                    $data['id'] = $this->idGenerator->generate($productId, $rawSelections);
                    $data['payload']['jvOptionSelections'] = $rawSelections;
                }
            }
        } elseif (is_array($rawSelections)) {
            $data['id'] = $this->idGenerator->generate($productId, $rawSelections);
            $data['payload']['jvOptionSelections'] = $rawSelections;
        }

        return $this->decorated->create($data, $context);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(LineItem $lineItem, array $data, SalesChannelContext $context): void
    {
        $this->decorated->update($lineItem, $data, $context);
    }
}
