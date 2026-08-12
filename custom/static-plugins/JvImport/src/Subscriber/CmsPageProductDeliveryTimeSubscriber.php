<?php declare(strict_types=1);

namespace Jv\Import\Subscriber;

use Jv\Import\Service\ProductDeliveryTime\AttachSalesChannelProductDeliveryTimesToStoreApiProductsService;
use Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent;
use Shopware\Core\Content\Cms\SalesChannel\Struct\BuyBoxStruct;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductBoxStruct;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductDescriptionReviewsStruct;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopware\Core\Content\Product\ProductEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class CmsPageProductDeliveryTimeSubscriber implements EventSubscriberInterface
{
    public function __construct(private AttachSalesChannelProductDeliveryTimesToStoreApiProductsService $attachDeliveryTimes)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [CmsPageLoadedEvent::class => 'onCmsPageLoaded'];
    }

    public function onCmsPageLoaded(CmsPageLoadedEvent $event): void
    {
        $products = [];

        foreach ($event->getResult() as $page) {
            foreach ($page->getElementsOfType('product-slider') as $slot) {
                $slider = $slot->getData();
                if (!$slider instanceof ProductSliderStruct || null === $slider->getProducts()) {
                    continue;
                }

                foreach ($slider->getProducts() as $product) {
                    $products[$product->getId()] = $product;
                }
            }

            foreach (['product-box', 'buy-box', 'product-description-reviews'] as $type) {
                foreach ($page->getElementsOfType($type) as $slot) {
                    $data = $slot->getData();
                    $product = match (true) {
                        $data instanceof ProductBoxStruct => $data->getProduct(),
                        $data instanceof BuyBoxStruct => $data->getProduct(),
                        $data instanceof ProductDescriptionReviewsStruct => $data->getProduct(),
                        default => null,
                    };

                    if ($product instanceof ProductEntity) {
                        $products[$product->getId()] = $product;
                    }
                }
            }
        }

        $this->attachDeliveryTimes->execute($products, $event->getSalesChannelContext());
    }
}
