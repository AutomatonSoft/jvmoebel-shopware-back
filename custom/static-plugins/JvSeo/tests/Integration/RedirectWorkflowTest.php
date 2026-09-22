<?php declare(strict_types=1);

namespace Jv\Seo\Tests\Integration;

use Jv\Seo\Contract\ImportImageRedirectData;
use Jv\Seo\Contract\ImportImageRedirectsInterface;
use Jv\Seo\Contract\ImportProductRedirectData;
use Jv\Seo\Contract\ImportProductRedirectsInterface;
use Jv\Seo\Service\Redirect\CategoryTargetUrlResolver;
use Jv\Seo\Service\Redirect\Exception\RedirectValidationException;
use Jv\Seo\Service\Redirect\ImageTargetUrlResolver;
use Jv\Seo\Service\Redirect\LandingPageTargetUrlResolver;
use Jv\Seo\Service\Redirect\LookupRedirectService;
use Jv\Seo\Service\Redirect\ProductTargetUrlResolver;
use Jv\Seo\Service\Redirect\RedirectQueryService;
use Jv\Seo\Service\Redirect\SaveRedirectService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class RedirectWorkflowTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;
    private string $salesChannelId;
    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->browser = $this->createSalesChannelBrowser();
        $this->salesChannelId = $this->getSalesChannelApiSalesChannelId();
        $this->ids = new IdsCollection();
    }

    public function testImportedProductRedirectIsIdempotentAndResolvesToTheCanonicalProductUrl(): void
    {
        $productId = $this->createProduct('imported', $this->salesChannelId);
        $this->writeCanonicalSeoUrl($productId, $this->salesChannelId, 'sofa/canonical-product');
        $sourceUrl = 'https://WWW.JVMOEBEL.DE/Chestefield+Sofa+Ecksofa.htm';
        $data = new ImportProductRedirectData('cosmoshop', 'jvmoebel.de', '17952', $productId, $this->salesChannelId, $sourceUrl);

        $first = $this->importer()->import([$data], Context::createDefaultContext());
        $second = $this->importer()->import([$data], Context::createDefaultContext());

        self::assertSame(1, $first->created);
        self::assertSame(0, $first->conflicts);
        self::assertSame(1, $second->unchanged);
        self::assertCount(1, $this->query()->list('product', 'Chestefield+Sofa', null, null, null, null, 1, 25, Context::createDefaultContext())['data']);

        $decision = $this->lookup()->lookup($sourceUrl, $this->salesChannelId, Context::createDefaultContext());
        self::assertNotNull($decision);
        self::assertSame('product', $decision['type']);
        self::assertSame($productId, $decision['productId']);
        self::assertNull($decision['categoryId']);
        self::assertNull($decision['mediaId']);
        self::assertSame(
            $this->targetResolver()->resolve($productId, $this->salesChannelId, $sourceUrl),
            $decision['targetUrl'],
        );

        $this->browser->request(
            'POST',
            '/store-api/jv-seo/redirect',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['url' => $sourceUrl], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(200, $this->browser->getResponse()->getStatusCode());
        /** @var array{data: array{statusCode: int, targetUrl: string}} $response */
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(301, $response['data']['statusCode']);
        self::assertSame($decision['targetUrl'], $response['data']['targetUrl']);
    }

    public function testSameCosmoShopArticleIdFromDifferentMarketsDoesNotMixRedirects(): void
    {
        $austrianChannel = $this->createSalesChannel([
            'id' => Uuid::randomHex(),
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'https://www.jvmoebel.at',
            ]],
        ]);
        $germanProductId = $this->createProduct('german', $this->salesChannelId);
        $austrianProductId = $this->createProduct('austrian', $austrianChannel['id']);

        $result = $this->importer()->import([
            new ImportProductRedirectData(
                'cosmoshop',
                'jvmoebel.de',
                '17952',
                $germanProductId,
                $this->salesChannelId,
                'https://www.jvmoebel.de/German+Sofa.htm',
            ),
            new ImportProductRedirectData(
                'cosmoshop',
                'jvmoebel.at',
                '17952',
                $austrianProductId,
                $austrianChannel['id'],
                'https://www.jvmoebel.at/Austrian+Sofa.htm',
            ),
        ], Context::createDefaultContext());

        self::assertSame(2, $result->created);
        self::assertSame(0, $result->conflicts);
        self::assertSame(
            $germanProductId,
            $this->lookup()->lookup('https://www.jvmoebel.de/German+Sofa.htm', $this->salesChannelId, Context::createDefaultContext())['productId'] ?? null,
        );
        self::assertSame(
            $austrianProductId,
            $this->lookup()->lookup('https://www.jvmoebel.at/Austrian+Sofa.htm', $austrianChannel['id'], Context::createDefaultContext())['productId'] ?? null,
        );
    }

    public function testManualProductRedirectEditIsPreservedOnTheNextImport(): void
    {
        $productId = $this->createProduct('manual', $this->salesChannelId);
        $original = new ImportProductRedirectData(
            'cosmoshop',
            'jvmoebel.de',
            '35494',
            $productId,
            $this->salesChannelId,
            'https://www.jvmoebel.de/Original+Product.htm',
        );
        self::assertSame(1, $this->importer()->import([$original], Context::createDefaultContext())->created);

        $list = $this->query()->list('product', '', $productId, null, null, null, 1, 25, Context::createDefaultContext());
        self::assertCount(1, $list['data']);
        $redirect = $list['data'][0];
        $channel = $redirect['channels'][0];
        $source = $channel['sources'][0];
        $this->save()->update($redirect['id'], [
            'type' => 'product',
            'productId' => $productId,
            'channels' => [[
                'salesChannelId' => $this->salesChannelId,
                'enabled' => true,
                'sources' => [[
                    'id' => $source['id'],
                    'url' => 'https://www.jvmoebel.de/Manually+Corrected+Product.htm',
                ]],
            ]],
        ], Context::createDefaultContext());

        $changedSource = new ImportProductRedirectData(
            'cosmoshop',
            'jvmoebel.de',
            '35494',
            $productId,
            $this->salesChannelId,
            'https://www.jvmoebel.de/Changed+By+Source.htm',
        );
        $result = $this->importer()->import([$changedSource], Context::createDefaultContext());

        self::assertSame(1, $result->manualPreserved);
        self::assertNotNull($this->lookup()->lookup(
            'https://www.jvmoebel.de/Manually+Corrected+Product.htm',
            $this->salesChannelId,
            Context::createDefaultContext(),
        ));
        self::assertNull($this->lookup()->lookup(
            'https://www.jvmoebel.de/Changed+By+Source.htm',
            $this->salesChannelId,
            Context::createDefaultContext(),
        ));
    }

    public function testSourceUrlConflictDoesNotCreateAnEmptyAggregateForAnotherProduct(): void
    {
        $firstProductId = $this->createProduct('conflict-first', $this->salesChannelId);
        $secondProductId = $this->createProduct('conflict-second', $this->salesChannelId);
        $sourceUrl = 'https://www.jvmoebel.de/Shared+Legacy+Url.htm';

        self::assertSame(1, $this->importer()->import([
            new ImportProductRedirectData('cosmoshop', 'jvmoebel.de', '100', $firstProductId, $this->salesChannelId, $sourceUrl),
        ], Context::createDefaultContext())->created);
        $conflict = $this->importer()->import([
            new ImportProductRedirectData('cosmoshop', 'jvmoebel.de', '200', $secondProductId, $this->salesChannelId, $sourceUrl),
        ], Context::createDefaultContext());

        self::assertSame(1, $conflict->conflicts);
        self::assertSame('source_url_conflict', $conflict->issues[0]['code']);
        self::assertSame(
            $firstProductId,
            $this->lookup()->lookup($sourceUrl, $this->salesChannelId, Context::createDefaultContext())['productId'] ?? null,
        );
        self::assertNull(static::getContainer()->get('jv_seo_redirect.repository')->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('productId', $secondProductId)),
            Context::createDefaultContext(),
        )->firstId());
    }

    public function testManuallyRemovedImportedSourceRemainsATombstone(): void
    {
        $productId = $this->createProduct('tombstone', $this->salesChannelId);
        $firstUrl = 'https://www.jvmoebel.de/First+Historical+Url.htm';
        $removedUrl = 'https://www.jvmoebel.de/Removed+Historical+Url.htm';
        $result = $this->importer()->import([
            new ImportProductRedirectData('cosmoshop', 'jvmoebel.de', '100', $productId, $this->salesChannelId, $firstUrl),
            new ImportProductRedirectData('cosmoshop', 'jvmoebel.de', '200', $productId, $this->salesChannelId, $removedUrl),
        ], Context::createDefaultContext());
        self::assertSame(2, $result->created);

        $redirect = $this->query()->list('product', '', $productId, null, null, null, 1, 25, Context::createDefaultContext())['data'][0];
        $channel = $redirect['channels'][0];
        $firstSource = null;
        foreach ($channel['sources'] as $source) {
            if ($firstUrl === $source['url']) {
                $firstSource = $source;
                break;
            }
        }
        self::assertIsArray($firstSource);
        $this->save()->update($redirect['id'], [
            'type' => 'product',
            'productId' => $productId,
            'channels' => [[
                'salesChannelId' => $this->salesChannelId,
                'enabled' => true,
                'sources' => [['id' => $firstSource['id'], 'url' => $firstUrl]],
            ]],
        ], Context::createDefaultContext());

        $reimport = $this->importer()->import([
            new ImportProductRedirectData(
                'cosmoshop',
                'jvmoebel.de',
                '200',
                $productId,
                $this->salesChannelId,
                'https://www.jvmoebel.de/Source+Changed+After+Removal.htm',
            ),
        ], Context::createDefaultContext());

        self::assertSame(1, $reimport->manualPreserved);
        self::assertNull($this->lookup()->lookup($removedUrl, $this->salesChannelId, Context::createDefaultContext()));
        self::assertNull($this->lookup()->lookup(
            'https://www.jvmoebel.de/Source+Changed+After+Removal.htm',
            $this->salesChannelId,
            Context::createDefaultContext(),
        ));
    }

    public function testGeneralRedirectSupportsMultipleSourcesAndRejectsInvalidUrls(): void
    {
        static::getContainer()->get('sales_channel.repository')->update([[
            'id' => $this->salesChannelId,
            'name' => 'JVMöbel Deutschland',
        ]], Context::createDefaultContext());
        $redirectId = $this->save()->create([
            'type' => 'general',
            'channels' => [[
                'salesChannelId' => $this->salesChannelId,
                'enabled' => true,
                'targetUrl' => 'https://www.jvmoebel.de/new-page',
                'sources' => [
                    ['url' => 'http://www.jvmoebel.de/Old+Page.htm'],
                    ['url' => 'https://www.jvmoebel.de/Older+Page.htm'],
                ],
            ]],
        ], Context::createDefaultContext());

        $detail = $this->query()->detail($redirectId, new Context(
            new SystemSource(),
            languageIdChain: [Uuid::randomHex()],
        ));
        self::assertNotNull($detail);
        self::assertCount(2, $detail['channels'][0]['sources']);
        self::assertSame('JVMöbel Deutschland', $detail['channels'][0]['salesChannelName']);
        self::assertSame(
            'https://www.jvmoebel.de/new-page',
            $this->lookup()->lookup(
                'http://www.jvmoebel.de/Old+Page.htm',
                $this->salesChannelId,
                Context::createDefaultContext(),
            )['targetUrl'] ?? null,
        );

        try {
            $this->save()->create([
                'type' => 'general',
                'channels' => [[
                    'salesChannelId' => $this->salesChannelId,
                    'enabled' => true,
                    'targetUrl' => '/relative-target',
                    'sources' => [['url' => '']],
                ]],
            ], Context::createDefaultContext());
            self::fail('Invalid and empty URLs must be rejected.');
        } catch (RedirectValidationException $exception) {
            self::assertCount(2, $exception->violations());
        }
    }

    public function testManualCategoryRedirectResolvesToCanonicalCategoryUrl(): void
    {
        $categoryId = $this->createCategory('living-room');
        $this->writeCanonicalCategorySeoUrl($categoryId, $this->salesChannelId, 'sofas/chesterfield');
        $sourceUrl = 'https://www.jvmoebel.de/Sofas+-+Couches/Chesterfield/';

        $redirectId = $this->save()->create([
            'type' => 'category',
            'categoryId' => $categoryId,
            'channels' => [[
                'salesChannelId' => $this->salesChannelId,
                'enabled' => true,
                'sources' => [['url' => $sourceUrl]],
            ]],
        ], Context::createDefaultContext());

        $list = $this->query()->list('category', 'living-room', null, $categoryId, null, null, 1, 25, Context::createDefaultContext());
        self::assertCount(1, $list['data']);
        self::assertSame($redirectId, $list['data'][0]['id']);
        self::assertSame($categoryId, $list['data'][0]['categoryId']);
        self::assertSame('living-room', $list['data'][0]['categoryName']);
        self::assertNull($list['data'][0]['productId']);

        $decision = $this->lookup()->lookup($sourceUrl, $this->salesChannelId, Context::createDefaultContext());
        self::assertNotNull($decision);
        self::assertSame('category', $decision['type']);
        self::assertSame($categoryId, $decision['categoryId']);
        self::assertNull($decision['productId']);
        self::assertNull($decision['mediaId']);
        self::assertSame(
            $this->categoryTargetResolver()->resolve($categoryId, $this->salesChannelId, $sourceUrl),
            $decision['targetUrl'],
        );

        $this->browser->request(
            'POST',
            '/store-api/jv-seo/redirect',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['url' => $sourceUrl], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(200, $this->browser->getResponse()->getStatusCode());
        /** @var array{data: array{statusCode: int, type: string, targetUrl: string, productId: ?string, categoryId: ?string}} $response */
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(301, $response['data']['statusCode']);
        self::assertSame('category', $response['data']['type']);
        self::assertSame($decision['targetUrl'], $response['data']['targetUrl']);
        self::assertSame($categoryId, $response['data']['categoryId']);
        self::assertNull($response['data']['productId']);
    }

    public function testManualLandingPageRedirectResolvesToCanonicalLandingPageUrl(): void
    {
        $landingPageId = $this->createLandingPage('living-room-guide', $this->salesChannelId);
        $this->writeCanonicalLandingPageSeoUrl($landingPageId, $this->salesChannelId, 'guides/living-room');
        $sourceUrl = 'https://www.jvmoebel.de/Old-Living-Room-Guide.htm';

        $redirectId = $this->save()->create([
            'type' => 'pages',
            'landingPageId' => $landingPageId,
            'channels' => [[
                'salesChannelId' => $this->salesChannelId,
                'enabled' => true,
                'sources' => [['url' => $sourceUrl]],
            ]],
        ], Context::createDefaultContext());

        $list = $this->query()->list('pages', 'living-room-guide', null, null, $landingPageId, null, 1, 25, Context::createDefaultContext());
        self::assertCount(1, $list['data']);
        self::assertSame($redirectId, $list['data'][0]['id']);
        self::assertSame($landingPageId, $list['data'][0]['landingPageId']);
        self::assertSame('living-room-guide', $list['data'][0]['landingPageName']);
        self::assertNull($list['data'][0]['productId']);
        self::assertNull($list['data'][0]['categoryId']);
        self::assertNull($list['data'][0]['mediaId']);

        $decision = $this->lookup()->lookup($sourceUrl, $this->salesChannelId, Context::createDefaultContext());
        self::assertNotNull($decision);
        self::assertSame('pages', $decision['type']);
        self::assertSame($landingPageId, $decision['landingPageId']);
        self::assertNull($decision['productId']);
        self::assertNull($decision['categoryId']);
        self::assertNull($decision['mediaId']);
        self::assertSame(
            $this->landingPageTargetResolver()->resolve($landingPageId, $this->salesChannelId, $sourceUrl),
            $decision['targetUrl'],
        );

        $this->browser->request(
            'POST',
            '/store-api/jv-seo/redirect',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['url' => $sourceUrl], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(200, $this->browser->getResponse()->getStatusCode());
        /** @var array{data: array{statusCode: int, type: string, targetUrl: string, landingPageId: ?string}} $response */
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(301, $response['data']['statusCode']);
        self::assertSame('pages', $response['data']['type']);
        self::assertSame($decision['targetUrl'], $response['data']['targetUrl']);
        self::assertSame($landingPageId, $response['data']['landingPageId']);
    }

    public function testManualImageRedirectResolvesToCurrentPublicMediaUrl(): void
    {
        $mediaId = $this->createImage('legacy-redirect-image');
        $sourceUrl = 'https://www.jvmoebel.de/legacy/images/old-sofa.jpg';
        $context = Context::createDefaultContext();
        $targetUrl = $this->imageTargetResolver()->resolve($mediaId, $context);
        self::assertNotNull($targetUrl);

        $redirectId = $this->save()->create([
            'type' => 'image',
            'mediaId' => $mediaId,
            'channels' => [[
                'salesChannelId' => $this->salesChannelId,
                'enabled' => true,
                'sources' => [['url' => $sourceUrl]],
            ]],
        ], $context);

        $list = $this->query()->list('image', 'legacy-redirect-image', null, null, null, $mediaId, 1, 25, $context);
        self::assertCount(1, $list['data']);
        self::assertSame($redirectId, $list['data'][0]['id']);
        self::assertSame($mediaId, $list['data'][0]['mediaId']);
        self::assertSame('legacy-redirect-image.jpg', $list['data'][0]['imageName']);
        self::assertNull($list['data'][0]['productId']);
        self::assertNull($list['data'][0]['categoryId']);
        self::assertSame($targetUrl, $list['data'][0]['channels'][0]['targetUrl']);

        $decision = $this->lookup()->lookup($sourceUrl, $this->salesChannelId, $context);
        self::assertNotNull($decision);
        self::assertSame('image', $decision['type']);
        self::assertSame($mediaId, $decision['mediaId']);
        self::assertNull($decision['productId']);
        self::assertNull($decision['categoryId']);
        self::assertSame($targetUrl, $decision['targetUrl']);

        $this->browser->request(
            'POST',
            '/store-api/jv-seo/redirect',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['url' => $sourceUrl], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(200, $this->browser->getResponse()->getStatusCode());
        /** @var array{data: array{statusCode: int, type: string, targetUrl: string, productId: ?string, categoryId: ?string, mediaId: ?string}} $response */
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(301, $response['data']['statusCode']);
        self::assertSame('image', $response['data']['type']);
        self::assertSame($targetUrl, $response['data']['targetUrl']);
        self::assertSame($mediaId, $response['data']['mediaId']);
    }

    public function testImportedImageRedirectIsIdempotentAndResolvesToCurrentPublicMediaUrl(): void
    {
        $mediaId = $this->createImage('imported-legacy-image');
        $sourceUrl = 'https://www.jvmoebel.de/cosmoshop/default/pix/a/n/1742011895-159847-1.3.jpg';
        $context = Context::createDefaultContext();
        $data = new ImportImageRedirectData(
            'cosmoshop',
            'jvmoebel.de',
            hash('sha256', 'SKU-1\0main:1742011895-159847.3.jpg\0'.$sourceUrl),
            $mediaId,
            $this->salesChannelId,
            $sourceUrl,
        );

        $first = $this->imageImporter()->import([$data], $context);
        $second = $this->imageImporter()->import([$data], $context);

        self::assertSame(1, $first->created);
        self::assertSame(0, $first->conflicts);
        self::assertSame(1, $second->unchanged);

        $decision = $this->lookup()->lookup($sourceUrl, $this->salesChannelId, $context);
        self::assertNotNull($decision);
        self::assertSame('image', $decision['type']);
        self::assertSame($mediaId, $decision['mediaId']);
        self::assertSame($this->imageTargetResolver()->resolve($mediaId, $context), $decision['targetUrl']);
    }

    private function createProduct(string $key, string $salesChannelId): string
    {
        $builder = (new ProductBuilder($this->ids, $key))
            ->name('SEO redirect test '.$key)
            ->price(100)
            ->visibility($salesChannelId, ProductVisibilityDefinition::VISIBILITY_ALL);
        $builder->write(static::getContainer());

        return $this->ids->get($key);
    }

    private function createCategory(string $name): string
    {
        $id = Uuid::randomHex();
        static::getContainer()->get('category.repository')->create([[
            'id' => $id,
            'name' => $name,
            'active' => true,
        ]], Context::createDefaultContext());

        return $id;
    }

    private function createLandingPage(string $name, string $salesChannelId): string
    {
        $id = Uuid::randomHex();
        static::getContainer()->get('landing_page.repository')->create([[
            'id' => $id,
            'name' => $name,
            'url' => $name,
            'active' => true,
            'salesChannels' => [['id' => $salesChannelId]],
        ]], Context::createDefaultContext());

        return $id;
    }

    private function createImage(string $fileName): string
    {
        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $context->scope(Context::SYSTEM_SCOPE, static function (Context $systemContext) use ($id, $fileName): void {
            static::getContainer()->get('media.repository')->create([[
                'id' => $id,
                'fileName' => $fileName,
                'fileExtension' => 'jpg',
                'mimeType' => 'image/jpeg',
                'fileSize' => 100,
                'private' => false,
                'path' => 'media/'.$fileName.'.jpg',
            ]], $systemContext);
        });

        return $id;
    }

    private function writeCanonicalSeoUrl(string $productId, string $salesChannelId, string $seoPathInfo): void
    {
        $repository = static::getContainer()->get('seo_url.repository');
        $context = Context::createDefaultContext();
        $ids = $repository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('foreignKey', $productId)),
            $context,
        )->getIds();
        if ([] !== $ids) {
            $repository->delete(array_map(static fn (string $id): array => ['id' => $id], $ids), $context);
        }

        $repository->create([[
            'id' => Uuid::randomHex(),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'salesChannelId' => $salesChannelId,
            'foreignKey' => $productId,
            'routeName' => 'frontend.detail.page',
            'pathInfo' => '/detail/'.$productId,
            'seoPathInfo' => $seoPathInfo,
            'isCanonical' => true,
            'isDeleted' => false,
        ]], $context);
    }

    private function writeCanonicalCategorySeoUrl(string $categoryId, string $salesChannelId, string $seoPathInfo): void
    {
        static::getContainer()->get('seo_url.repository')->create([[
            'id' => Uuid::randomHex(),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'salesChannelId' => $salesChannelId,
            'foreignKey' => $categoryId,
            'routeName' => 'frontend.navigation.page',
            'pathInfo' => '/navigation/'.$categoryId,
            'seoPathInfo' => $seoPathInfo,
            'isCanonical' => true,
            'isDeleted' => false,
        ]], Context::createDefaultContext());
    }

    private function writeCanonicalLandingPageSeoUrl(string $landingPageId, string $salesChannelId, string $seoPathInfo): void
    {
        $repository = static::getContainer()->get('seo_url.repository');
        $context = Context::createDefaultContext();
        $ids = $repository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('foreignKey', $landingPageId)),
            $context,
        )->getIds();
        if ([] !== $ids) {
            $repository->delete(array_map(static fn (string $id): array => ['id' => $id], $ids), $context);
        }

        $repository->create([[
            'id' => Uuid::randomHex(),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'salesChannelId' => $salesChannelId,
            'foreignKey' => $landingPageId,
            'routeName' => 'frontend.landing.page',
            'pathInfo' => '/landing-page/'.$landingPageId,
            'seoPathInfo' => $seoPathInfo,
            'isCanonical' => true,
            'isDeleted' => false,
        ]], $context);
    }

    private function importer(): ImportProductRedirectsInterface
    {
        return static::getContainer()->get(ImportProductRedirectsInterface::class);
    }

    private function imageImporter(): ImportImageRedirectsInterface
    {
        return static::getContainer()->get(ImportImageRedirectsInterface::class);
    }

    private function save(): SaveRedirectService
    {
        return static::getContainer()->get(SaveRedirectService::class);
    }

    private function query(): RedirectQueryService
    {
        return static::getContainer()->get(RedirectQueryService::class);
    }

    private function lookup(): LookupRedirectService
    {
        return static::getContainer()->get(LookupRedirectService::class);
    }

    private function targetResolver(): ProductTargetUrlResolver
    {
        return static::getContainer()->get(ProductTargetUrlResolver::class);
    }

    private function categoryTargetResolver(): CategoryTargetUrlResolver
    {
        return static::getContainer()->get(CategoryTargetUrlResolver::class);
    }

    private function imageTargetResolver(): ImageTargetUrlResolver
    {
        return static::getContainer()->get(ImageTargetUrlResolver::class);
    }

    private function landingPageTargetResolver(): LandingPageTargetUrlResolver
    {
        return static::getContainer()->get(LandingPageTargetUrlResolver::class);
    }
}
