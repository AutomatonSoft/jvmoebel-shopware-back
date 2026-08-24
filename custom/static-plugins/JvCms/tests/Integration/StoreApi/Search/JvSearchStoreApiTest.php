<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration\StoreApi\Search;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Store API functional coverage for jv-search routes (SPEC-004 / SPEC-006 review checklist).
 */
final class JvSearchStoreApiTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private string $salesChannelId;

    private IdsCollection $ids;

    private string $token;

    protected function setUp(): void
    {
        $this->browser = $this->createSalesChannelBrowser();
        $this->salesChannelId = $this->getSalesChannelApiSalesChannelId();
        $this->ids = new IdsCollection();
        $this->token = 'jv-search-'.Uuid::randomHex();
    }

    public function testSuggestMissingSearchReturns400(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/jv-search/suggest',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{}',
        );

        self::assertSame(400, $this->browser->getResponse()->getStatusCode());
    }

    public function testSuggestEmptySearchReturns200WithEmptyProducts(): void
    {
        $payload = $this->jsonPost('/store-api/jv-search/suggest', [
            'search' => '   ',
            'limit' => 10,
        ]);

        self::assertSame(200, $this->browser->getResponse()->getStatusCode());
        self::assertSame('', $payload['query'] ?? null);
        self::assertSame([], $payload['products'] ?? null);
        self::assertSame([], $payload['interpretedFilters'] ?? null);
        self::assertSame('jv_search_suggest_result', $payload['apiAlias'] ?? null);
        self::assertArrayNotHasKey('listing', $payload);
    }

    public function testFullSearchMissingAndEmptySearchReturn400(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/jv-search',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{}',
        );
        self::assertSame(400, $this->browser->getResponse()->getStatusCode());

        $this->browser->request(
            'POST',
            '/store-api/jv-search',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['search' => '  '], \JSON_THROW_ON_ERROR),
        );
        self::assertSame(400, $this->browser->getResponse()->getStatusCode());
    }

    public function testFullSearchResponseIsFlatAndIncludesContractFields(): void
    {
        $this->createSearchableProduct('a', 'Jv Search Sofa A', categoryKey: 'cat-a', optionKey: 'brown');
        $this->createSearchableProduct('b', 'Jv Search Sofa B', categoryKey: 'cat-b', optionKey: 'brown');

        $payload = $this->jsonPost('/store-api/jv-search', [
            'search' => $this->token,
            'page' => 1,
            'limit' => 1,
            'order' => 'name-asc',
            'properties' => $this->ids->get('brown'),
        ]);

        self::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());
        self::assertSame($this->token, $payload['query'] ?? null);
        self::assertSame('jv_search_result', $payload['apiAlias'] ?? null);
        self::assertArrayNotHasKey('listing', $payload);
        self::assertArrayHasKey('products', $payload);
        self::assertArrayHasKey('total', $payload);
        self::assertArrayHasKey('page', $payload);
        self::assertArrayHasKey('limit', $payload);
        self::assertArrayHasKey('aggregations', $payload);
        self::assertArrayHasKey('interpretedFilters', $payload);
        self::assertArrayHasKey('remainingSearchTerm', $payload);
        self::assertIsArray($payload['products']);
        self::assertGreaterThanOrEqual(2, $payload['total']);
        self::assertSame(1, $payload['page']);
        self::assertSame(1, $payload['limit']);
        self::assertCount(1, $payload['products']);

        // Page 2 still reports the same total (paging via Shopware "p").
        $page2 = $this->jsonPost('/store-api/jv-search', [
            'search' => $this->token,
            'page' => 2,
            'limit' => 1,
            'order' => 'name-asc',
            'properties' => $this->ids->get('brown'),
        ]);
        self::assertSame(200, $this->browser->getResponse()->getStatusCode());
        self::assertSame($payload['total'], $page2['total']);
        self::assertSame(2, $page2['page']);
        self::assertNotSame(
            $payload['products'][0]['id'] ?? null,
            $page2['products'][0]['id'] ?? null,
        );

        // Cross-category: both fixtures are returned across pages.
        $ids = [
            $payload['products'][0]['id'] ?? null,
            $page2['products'][0]['id'] ?? null,
        ];
        self::assertContains($this->ids->get('a'), $ids);
        self::assertContains($this->ids->get('b'), $ids);

        // Property aggregation / active filters should be present for a filtered listing.
        self::assertNotEmpty($payload['aggregations']);
    }

    public function testSuggestReturnsChannelScopedSeoUrlPriceAndCurrency(): void
    {
        $productId = $this->createSearchableProduct('suggest', 'Jv Suggest Sofa', categoryKey: 'cat-suggest', optionKey: 'leather');
        $this->writeSeoUrls($productId);

        $payload = $this->jsonPost('/store-api/jv-search/suggest', [
            'search' => $this->token,
            'limit' => 999,
        ]);

        self::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());
        self::assertSame('jv_search_suggest_result', $payload['apiAlias'] ?? null);
        self::assertIsArray($payload['products'] ?? null);
        self::assertNotEmpty($payload['products']);

        $product = null;
        foreach ($payload['products'] as $row) {
            if (($row['id'] ?? null) === $productId) {
                $product = $row;
                break;
            }
        }
        self::assertIsArray($product);
        self::assertSame('/jv-suggest-sofa-canonical', $product['seoUrl'] ?? null);
        self::assertIsArray($product['price'] ?? null);
        self::assertArrayHasKey('gross', $product['price']);
        self::assertArrayHasKey('net', $product['price']);
        self::assertSame(Defaults::CURRENCY, $product['price']['currencyId'] ?? null);
        self::assertLessThanOrEqual(20, \count($payload['products']));
    }

    public function testProductFromOtherSalesChannelIsNotReturned(): void
    {
        $otherChannel = $this->createSalesChannel([
            'id' => Uuid::randomHex(),
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://other-jv-search.test',
            ]],
        ]);

        $builder = (new ProductBuilder($this->ids, 'other-'.$this->token))
            ->name('Other Channel Only '.$this->token)
            ->price(99)
            ->visibility($otherChannel['id'], ProductVisibilityDefinition::VISIBILITY_ALL);
        $builder->write(static::getContainer());
        $this->indexKeyword($this->ids->get('other-'.$this->token), $this->token);

        $payload = $this->jsonPost('/store-api/jv-search', [
            'search' => $this->token,
            'limit' => 50,
        ]);

        self::assertSame(200, $this->browser->getResponse()->getStatusCode());
        foreach ($payload['products'] as $row) {
            self::assertNotSame($this->ids->get('other-'.$this->token), $row['id'] ?? null);
        }
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function jsonPost(string $path, array $body): array
    {
        $this->browser->request(
            'POST',
            $path,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($body, \JSON_THROW_ON_ERROR),
        );

        $content = (string) $this->browser->getResponse()->getContent();
        if ('' === $content) {
            return [];
        }

        $decoded = json_decode($content, true);
        self::assertIsArray($decoded);

        return $decoded;
    }

    private function createSearchableProduct(
        string $key,
        string $name,
        string $categoryKey,
        string $optionKey,
    ): string {
        $builder = (new ProductBuilder($this->ids, $key))
            ->name($name.' '.$this->token)
            ->price(120)
            ->category($categoryKey)
            ->property($optionKey, 'color')
            ->visibility($this->salesChannelId, ProductVisibilityDefinition::VISIBILITY_ALL);

        $builder->write(static::getContainer());
        $productId = $this->ids->get($key);
        $this->indexKeyword($productId, $this->token);

        return $productId;
    }

    private function writeSeoUrls(string $productId): void
    {
        $wrongChannel = $this->createSalesChannel([
            'id' => Uuid::randomHex(),
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://wrong-seo-jv-search.test',
            ]],
        ]);

        // Drop auto-generated SEO rows so we control channel selection explicitly.
        static::getContainer()->get('seo_url.repository')->delete(
            array_map(
                static fn (string $id): array => ['id' => $id],
                static::getContainer()->get('seo_url.repository')->searchIds(
                    (new Criteria())->addFilter(new EqualsFilter('foreignKey', $productId)),
                    Context::createDefaultContext(),
                )->getIds(),
            ),
            Context::createDefaultContext(),
        );

        // uniq.seo_url.foreign_key allows only one row per (language, channel, foreign_key, route, is_canonical).
        static::getContainer()->get('seo_url.repository')->create([
            [
                'id' => Uuid::randomHex(),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'salesChannelId' => $this->salesChannelId,
                'foreignKey' => $productId,
                'routeName' => 'frontend.detail.page',
                'pathInfo' => '/detail/'.$productId,
                'seoPathInfo' => 'jv-suggest-sofa-NON-CANONICAL',
                'isCanonical' => false,
                'isDeleted' => false,
            ],
            [
                'id' => Uuid::randomHex(),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'salesChannelId' => $this->salesChannelId,
                'foreignKey' => $productId,
                'routeName' => 'frontend.navigation.page',
                'pathInfo' => '/navigation/'.$productId,
                'seoPathInfo' => 'jv-suggest-sofa-WRONG-ROUTE',
                'isCanonical' => true,
                'isDeleted' => false,
            ],
            [
                'id' => Uuid::randomHex(),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'salesChannelId' => $wrongChannel['id'],
                'foreignKey' => $productId,
                'routeName' => 'frontend.detail.page',
                'pathInfo' => '/detail/'.$productId,
                'seoPathInfo' => 'jv-suggest-sofa-WRONG-CHANNEL',
                'isCanonical' => true,
            ],
            [
                'id' => Uuid::randomHex(),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'salesChannelId' => $this->salesChannelId,
                'foreignKey' => $productId,
                'routeName' => 'frontend.detail.page',
                'pathInfo' => '/detail/'.$productId,
                'seoPathInfo' => 'jv-suggest-sofa-canonical',
                'isCanonical' => true,
            ],
        ], Context::createDefaultContext());
    }

    private function indexKeyword(string $productId, string $keyword): void
    {
        static::getContainer()->get('product_search_keyword.repository')->create([[
            'id' => Uuid::randomHex(),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'productId' => $productId,
            'productVersionId' => Defaults::LIVE_VERSION,
            'keyword' => mb_strtolower($keyword),
            'ranking' => 1000.0,
        ]], Context::createDefaultContext());
    }

    public function testUnknownOrderDoesNotReturn503(): void
    {
        $this->createSearchableProduct('sort', 'Jv Sort Sofa', categoryKey: 'cat-sort', optionKey: 'sort-opt');

        $this->jsonPost('/store-api/jv-search', [
            'search' => $this->token,
            'order' => 'this-sorting-does-not-exist',
        ]);

        // Shopware falls back to default sorting for unknown keys (200), but must never wrap as 503.
        self::assertSame(200, $this->browser->getResponse()->getStatusCode());
    }

    public function testPageOutOfRangeReturnsClientErrorNot503(): void
    {
        $this->createSearchableProduct('page', 'Jv Page Sofa', categoryKey: 'cat-page', optionKey: 'page-opt');

        $this->jsonPost('/store-api/jv-search', [
            'search' => $this->token,
            'page' => 9999,
            'limit' => 1,
        ]);

        $status = $this->browser->getResponse()->getStatusCode();
        self::assertGreaterThanOrEqual(400, $status);
        self::assertLessThan(500, $status);
    }
}
