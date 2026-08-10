<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ButtonCmsElementResolver;
use Jv\Cms\DataResolver\Element\ButtonStruct;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class ButtonCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        // 1) DI: сервис реально в контейнере (не new ...)
        $resolver = $container->get(ButtonCmsElementResolver::class);
        self::assertInstanceOf(ButtonCmsElementResolver::class, $resolver);
        self::assertSame('jv-button', $resolver->getType());

        // 2) CMS pipeline: CmsSlotsDataResolver знает type jv-button
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->slot([
            'label' => '  Shop now  ',
            'url' => 'https://jvmoebel.de/angebote',
            'variant' => 'secondary',
            'openInNewTab' => true,
        ]);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $data = $resolved->get($slot->getUniqueIdentifier())?->getData();

        self::assertInstanceOf(ButtonStruct::class, $data);
        self::assertSame('Shop now', $data->getLabel());
        self::assertSame('https://jvmoebel.de/angebote', $data->getUrl());
        self::assertSame('secondary', $data->getVariant());
        self::assertTrue($data->isOpenInNewTab());
        self::assertSame('cms_jv_button', $data->getApiAlias());
    }

    /**
     * @param array{label?: string, url?: string, variant?: string, openInNewTab?: bool} $values
     */
    private function slot(array $values): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('label', FieldConfig::SOURCE_STATIC, $values['label'] ?? ''));
        $config->add(new FieldConfig('url', FieldConfig::SOURCE_STATIC, $values['url'] ?? ''));
        $config->add(new FieldConfig('variant', FieldConfig::SOURCE_STATIC, $values['variant'] ?? 'primary'));
        $config->add(new FieldConfig('openInNewTab', FieldConfig::SOURCE_STATIC, $values['openInNewTab'] ?? false));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-button-integration');
        $slot->setType(ButtonCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }
}
