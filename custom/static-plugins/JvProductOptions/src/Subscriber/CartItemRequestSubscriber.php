<?php declare(strict_types=1);

namespace Jv\ProductOptions\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class CartItemRequestSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 30],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->request->has('items')) {
            return;
        }

        $items = $request->request->all('items');
        $modified = false;

        foreach ($items as $index => $item) {
            if (\is_array($item) && isset($item['quantity']) && !\is_int($item['quantity']) && is_numeric($item['quantity'])) {
                $items[$index]['quantity'] = (int) $item['quantity'];
                $modified = true;
            }
        }

        if ($modified) {
            $request->request->set('items', $items);
        }
    }
}
