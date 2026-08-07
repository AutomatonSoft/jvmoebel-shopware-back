<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ButtonCmsElementResolver;
use Jv\Cms\DataResolver\Element\ButtonStruct;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class ButtonCmsElementResolverTest extends TestCase
{
    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new ButtonCmsElementResolver();

        self::assertSame('jv-button', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testItNormalizesStaticConfiguration(): void
    {
        $slot = $this->slot([
            'label' => '  Zu den Neuheiten  ',
            'url' => 'https://jvmoebel.de/neuheiten',
            'variant' => 'secondary',
            'openInNewTab' => true,
        ]);

        (new ButtonCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ButtonStruct::class, $data);
        self::assertSame('Zu den Neuheiten', $data->getLabel());
        self::assertSame('https://jvmoebel.de/neuheiten', $data->getUrl());
        self::assertSame('secondary', $data->getVariant());
        self::assertTrue($data->isOpenInNewTab());
        self::assertSame('cms_jv_button', $data->getApiAlias());
    }

    public function testItRejectsUnsafeOrIncompleteUrlsAndUnknownVariants(): void
    {
        $slot = $this->slot([
            'label' => 'Click',
            'url' => 'javascript:alert(1)',
            'variant' => 'huge',
            'openInNewTab' => false,
        ]);

        (new ButtonCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ButtonStruct::class, $data);
        self::assertNull($data->getUrl());
        self::assertSame('primary', $data->getVariant());
    }

    /**
     * @param array{label?: string, url?: string, variant?: string, openInNewTab?: bool} $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('label', FieldConfig::SOURCE_STATIC, $values['label'] ?? ''));
        $collection->add(new FieldConfig('url', FieldConfig::SOURCE_STATIC, $values['url'] ?? ''));
        $collection->add(new FieldConfig('variant', FieldConfig::SOURCE_STATIC, $values['variant'] ?? 'primary'));
        $collection->add(new FieldConfig('openInNewTab', FieldConfig::SOURCE_STATIC, $values['openInNewTab'] ?? false));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-button');
        $slot->setType(ButtonCmsElementResolver::TYPE);
        $slot->setFieldConfig($collection);

        return $slot;
    }

    private function resolverContext(): ResolverContext
    {
        return new ResolverContext(
            $this->createMock(SalesChannelContext::class),
            new Request(),
        );
    }
}
