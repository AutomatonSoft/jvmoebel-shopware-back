<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ExpertTipCmsElementResolver;
use Jv\Cms\DataResolver\Element\ExpertTipStruct;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class ExpertTipCmsElementResolverTest extends TestCase
{
    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new ExpertTipCmsElementResolver();

        self::assertSame('jv-expert-tip', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testEmptyConfigYieldsSafePayloadWithDefaultLabel(): void
    {
        $slot = $this->slot();
        (new ExpertTipCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ExpertTipStruct::class, $data);
        self::assertSame('cms_jv_expert_tip', $data->getApiAlias());
        self::assertSame('Tipp', $data->getLabel());
        self::assertSame('', $data->getTitle());
        self::assertSame('', $data->getBody());
    }

    public function testEmptyLabelFallsBackToTipp(): void
    {
        $slot = $this->slot(['label' => '']);
        (new ExpertTipCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ExpertTipStruct::class, $data);
        self::assertSame('Tipp', $data->getLabel());
    }

    public function testWhitespaceLabelFallsBackToTipp(): void
    {
        $slot = $this->slot(['label' => " \t "]);
        (new ExpertTipCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ExpertTipStruct::class, $data);
        self::assertSame('Tipp', $data->getLabel());
    }

    public function testItNormalizesHappyPath(): void
    {
        $slot = $this->slot([
            'label' => '  Pro-Tipp  ',
            'title' => '  Use coasters  ',
            'body' => '  Protect wood surfaces.  ',
        ]);

        (new ExpertTipCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ExpertTipStruct::class, $data);
        self::assertSame('Pro-Tipp', $data->getLabel());
        self::assertSame('Use coasters', $data->getTitle());
        self::assertSame('Protect wood surfaces.', $data->getBody());
    }

    public function testMalformedPersistedConfigTypesYieldSafePayload(): void
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('label', FieldConfig::SOURCE_STATIC, true));
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, ['broken']));
        $collection->add(new FieldConfig('body', FieldConfig::SOURCE_STATIC, 42));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-expert-tip-malformed');
        $slot->setType(ExpertTipCmsElementResolver::TYPE);
        $slot->setFieldConfig($collection);

        (new ExpertTipCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ExpertTipStruct::class, $data);
        self::assertSame('Tipp', $data->getLabel());
        self::assertSame('', $data->getTitle());
        self::assertSame('42', $data->getBody());
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'label' => '',
            'title' => 'Use coasters',
            'body' => 'Protect wood surfaces.',
        ]);

        (new ExpertTipCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ExpertTipStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_expert_tip', $payload['apiAlias']);
        self::assertSame('Tipp', $payload['label']);
        self::assertSame('Use coasters', $payload['title']);
        self::assertSame('Protect wood surfaces.', $payload['body']);
    }

    /**
     * @return array<string, mixed>
     */
    private function storeApiArray(Struct $struct): array
    {
        $payload = $struct->jsonSerialize();
        foreach ($payload as $key => $value) {
            if ($value instanceof Struct) {
                $payload[$key] = $this->storeApiArray($value);
            } elseif (\is_array($value)) {
                $payload[$key] = $this->storeApiList($value);
            }
        }

        $payload['apiAlias'] = $struct->getApiAlias();
        if (isset($payload['extensions']) && [] === $payload['extensions']) {
            unset($payload['extensions']);
        }

        return $payload;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private function storeApiList(array $values): array
    {
        foreach ($values as $key => $value) {
            if ($value instanceof Struct) {
                $values[$key] = $this->storeApiArray($value);
            } elseif (\is_array($value)) {
                $values[$key] = $this->storeApiList($value);
            }
        }

        return $values;
    }

    /**
     * @param array{
     *     label?: string,
     *     title?: string,
     *     body?: string
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('label', FieldConfig::SOURCE_STATIC, $values['label'] ?? ''));
        $collection->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $collection->add(new FieldConfig('body', FieldConfig::SOURCE_STATIC, $values['body'] ?? ''));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-expert-tip');
        $slot->setType(ExpertTipCmsElementResolver::TYPE);
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
