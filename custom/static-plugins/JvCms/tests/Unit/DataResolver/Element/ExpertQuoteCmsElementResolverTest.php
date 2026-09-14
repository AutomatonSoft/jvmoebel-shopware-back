<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Unit\DataResolver\Element;

use Jv\Cms\DataResolver\Element\ExpertQuoteCmsElementResolver;
use Jv\Cms\DataResolver\Element\ExpertQuoteStruct;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class ExpertQuoteCmsElementResolverTest extends TestCase
{
    public function testItExposesTheCmsElementType(): void
    {
        $resolver = new ExpertQuoteCmsElementResolver();

        self::assertSame('jv-expert-quote', $resolver->getType());
        self::assertNull($resolver->collect($this->slot(), $this->resolverContext()));
    }

    public function testEmptyConfigYieldsSafePayload(): void
    {
        $slot = $this->slot();
        (new ExpertQuoteCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ExpertQuoteStruct::class, $data);
        self::assertSame('cms_jv_expert_quote', $data->getApiAlias());
        self::assertSame('', $data->getQuote());
        self::assertSame('', $data->getAuthorName());
        self::assertSame('', $data->getAuthorRole());
    }

    public function testItNormalizesHappyPath(): void
    {
        $slot = $this->slot([
            'quote' => '  Quality starts with materials.  ',
            'authorName' => '  Anna Weber  ',
            'authorRole' => '  Interior stylist  ',
        ]);

        (new ExpertQuoteCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ExpertQuoteStruct::class, $data);
        self::assertSame('Quality starts with materials.', $data->getQuote());
        self::assertSame('Anna Weber', $data->getAuthorName());
        self::assertSame('Interior stylist', $data->getAuthorRole());
    }

    public function testMalformedPersistedConfigTypesYieldSafePayload(): void
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('quote', FieldConfig::SOURCE_STATIC, ['broken']));
        $collection->add(new FieldConfig('authorName', FieldConfig::SOURCE_STATIC, true));
        $collection->add(new FieldConfig('authorRole', FieldConfig::SOURCE_STATIC, 42));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-expert-quote-malformed');
        $slot->setType(ExpertQuoteCmsElementResolver::TYPE);
        $slot->setFieldConfig($collection);

        (new ExpertQuoteCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ExpertQuoteStruct::class, $data);
        self::assertSame('', $data->getQuote());
        self::assertSame('', $data->getAuthorName());
        self::assertSame('42', $data->getAuthorRole());
    }

    public function testSerializedStoreApiPayloadMatchesContract(): void
    {
        $slot = $this->slot([
            'quote' => 'Quality starts with materials.',
            'authorName' => 'Anna Weber',
            'authorRole' => 'Interior stylist',
        ]);

        (new ExpertQuoteCmsElementResolver())->enrich($slot, $this->resolverContext(), new ElementDataCollection());

        $data = $slot->getData();
        self::assertInstanceOf(ExpertQuoteStruct::class, $data);

        $payload = $this->storeApiArray($data);

        self::assertSame('cms_jv_expert_quote', $payload['apiAlias']);
        self::assertSame('Quality starts with materials.', $payload['quote']);
        self::assertSame('Anna Weber', $payload['authorName']);
        self::assertSame('Interior stylist', $payload['authorRole']);
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
     *     quote?: string,
     *     authorName?: string,
     *     authorRole?: string
     * } $values
     */
    private function slot(array $values = []): CmsSlotEntity
    {
        $collection = new FieldConfigCollection();
        $collection->add(new FieldConfig('quote', FieldConfig::SOURCE_STATIC, $values['quote'] ?? ''));
        $collection->add(new FieldConfig('authorName', FieldConfig::SOURCE_STATIC, $values['authorName'] ?? ''));
        $collection->add(new FieldConfig('authorRole', FieldConfig::SOURCE_STATIC, $values['authorRole'] ?? ''));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-expert-quote');
        $slot->setType(ExpertQuoteCmsElementResolver::TYPE);
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
