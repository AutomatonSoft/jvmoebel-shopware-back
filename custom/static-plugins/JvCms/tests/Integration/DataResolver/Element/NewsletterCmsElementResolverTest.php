<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\DataResolver\Element;

use Jv\Cms\DataResolver\Element\NewsletterCmsElementResolver;
use Jv\Cms\DataResolver\Element\NewsletterStruct;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class NewsletterCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolverIsRegisteredAndUsedByCmsPipeline(): void
    {
        $container = static::getContainer();

        $resolver = $container->get(NewsletterCmsElementResolver::class);
        self::assertInstanceOf(NewsletterCmsElementResolver::class, $resolver);
        self::assertSame('jv-newsletter', $resolver->getType());

        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = $container->get(CmsSlotsDataResolver::class);

        $slot = $this->createSlot([
            'title' => '  Join  ',
            'description' => '  Updates  ',
            'buttonLabel' => '  Subscribe  ',
            'buttonSize' => 'small',
            'placeholder' => '  Email  ',
            'storefrontUrl' => 'https://shop.example',
            'successMessage' => '  Ok  ',
            'invalidEmailMessage' => '  Bad email  ',
            'errorMessage' => '  Failed  ',
        ]);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $data = $resolved->get($slot->getUniqueIdentifier())?->getData();
        self::assertInstanceOf(NewsletterStruct::class, $data);
        self::assertSame('Join', $data->getTitle());
        self::assertSame('Updates', $data->getDescription());
        self::assertSame('Subscribe', $data->getButtonLabel());
        self::assertSame('small', $data->getButtonSize());
        self::assertSame('https://shop.example', $data->getStorefrontUrl());
        self::assertSame('cms_jv_newsletter', $data->getApiAlias());
    }

    public function testStoreApiEncoderExposesSerializedContract(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'title' => 'Useful ideas, occasionally.',
            'eyebrow' => 'The good-room letter',
            'description' => 'Room guides, material care and new pieces.',
            'buttonLabel' => 'Join us',
            'buttonSize' => 'large',
            'placeholder' => 'Your email address',
            'storefrontUrl' => 'https://www.example.com',
            'successMessage' => 'You are on the list.',
            'invalidEmailMessage' => 'Enter a valid email address.',
            'errorMessage' => 'Subscription failed. Please try again.',
        ]);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $resolvedSlot = $resolved->get($slot->getUniqueIdentifier());
        self::assertInstanceOf(CmsSlotEntity::class, $resolvedSlot);
        self::assertSame('jv-newsletter', $resolvedSlot->getType());

        $data = $resolvedSlot->getData();
        self::assertInstanceOf(NewsletterStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields([]));

        self::assertSame('cms_jv_newsletter', $payload['apiAlias']);
        self::assertSame('Useful ideas, occasionally.', $payload['title']);
        self::assertSame('The good-room letter', $payload['eyebrow']);
        self::assertSame('Room guides, material care and new pieces.', $payload['description']);
        self::assertSame('Join us', $payload['buttonLabel']);
        self::assertSame('large', $payload['buttonSize']);
        self::assertSame('Your email address', $payload['placeholder']);
        self::assertSame('https://www.example.com', $payload['storefrontUrl']);
        self::assertSame('You are on the list.', $payload['successMessage']);
        self::assertSame('Enter a valid email address.', $payload['invalidEmailMessage']);
        self::assertSame('Subscription failed. Please try again.', $payload['errorMessage']);
    }

    public function testMalformedStorefrontUrlEncodesAsNull(): void
    {
        /** @var CmsSlotsDataResolver $slotsResolver */
        $slotsResolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);

        $slot = $this->createSlot([
            'title' => '',
            'eyebrow' => '   ',
            'storefrontUrl' => 'javascript:alert(1)',
            'buttonSize' => 'huge',
        ]);

        $resolved = $slotsResolver->resolve(
            new CmsSlotCollection([$slot]),
            new ResolverContext(
                $this->createMock(SalesChannelContext::class),
                new Request(),
            ),
        );

        $data = $resolved->get($slot->getUniqueIdentifier())?->getData();
        self::assertInstanceOf(NewsletterStruct::class, $data);

        $payload = $encoder->encode($data, new ResponseFields([]));

        self::assertSame('cms_jv_newsletter', $payload['apiAlias']);
        self::assertSame('', $payload['title']);
        self::assertNull($payload['eyebrow']);
        self::assertNull($payload['storefrontUrl']);
        self::assertSame('medium', $payload['buttonSize']);
    }

    /**
     * @param array{
     *     title?: string,
     *     eyebrow?: string,
     *     description?: string,
     *     buttonLabel?: string,
     *     buttonSize?: string,
     *     placeholder?: string,
     *     storefrontUrl?: string,
     *     successMessage?: string,
     *     invalidEmailMessage?: string,
     *     errorMessage?: string
     * } $values
     */
    private function createSlot(array $values): CmsSlotEntity
    {
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('title', FieldConfig::SOURCE_STATIC, $values['title'] ?? ''));
        $config->add(new FieldConfig('eyebrow', FieldConfig::SOURCE_STATIC, $values['eyebrow'] ?? ''));
        $config->add(new FieldConfig('description', FieldConfig::SOURCE_STATIC, $values['description'] ?? ''));
        $config->add(new FieldConfig('buttonLabel', FieldConfig::SOURCE_STATIC, $values['buttonLabel'] ?? ''));
        $config->add(new FieldConfig('buttonSize', FieldConfig::SOURCE_STATIC, $values['buttonSize'] ?? 'medium'));
        $config->add(new FieldConfig('placeholder', FieldConfig::SOURCE_STATIC, $values['placeholder'] ?? ''));
        $config->add(new FieldConfig('storefrontUrl', FieldConfig::SOURCE_STATIC, $values['storefrontUrl'] ?? ''));
        $config->add(new FieldConfig('successMessage', FieldConfig::SOURCE_STATIC, $values['successMessage'] ?? ''));
        $config->add(new FieldConfig('invalidEmailMessage', FieldConfig::SOURCE_STATIC, $values['invalidEmailMessage'] ?? ''));
        $config->add(new FieldConfig('errorMessage', FieldConfig::SOURCE_STATIC, $values['errorMessage'] ?? ''));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-jv-newsletter-integration');
        $slot->setType(NewsletterCmsElementResolver::TYPE);
        $slot->setSlot('content');
        $slot->setFieldConfig($config);

        return $slot;
    }
}
