<?php

declare(strict_types=1);

namespace Jv\Cms\Tests\Integration;

use Jv\Cms\DataResolver\Element\AppDownloadPromoMediaStruct;
use Jv\Cms\DataResolver\Element\AppDownloadPromoStruct;
use Jv\Cms\DataResolver\Element\ArticleHeroMediaStruct;
use Jv\Cms\DataResolver\Element\ArticleHeroStruct;
use Jv\Cms\DataResolver\Element\AuthorFooterLinkStruct;
use Jv\Cms\DataResolver\Element\AuthorFooterMediaStruct;
use Jv\Cms\DataResolver\Element\AuthorFooterStruct;
use Jv\Cms\DataResolver\Element\ChipRailChipStruct;
use Jv\Cms\DataResolver\Element\ChipRailStruct;
use Jv\Cms\DataResolver\Element\ColorWorldPickerColorMediaStruct;
use Jv\Cms\DataResolver\Element\ColorWorldPickerColorStruct;
use Jv\Cms\DataResolver\Element\ColorWorldPickerStruct;
use Jv\Cms\DataResolver\Element\CountdownPromoLinkStruct;
use Jv\Cms\DataResolver\Element\CountdownPromoStruct;
use Jv\Cms\DataResolver\Element\CrossRoomSectionRoomMediaStruct;
use Jv\Cms\DataResolver\Element\CrossRoomSectionRoomStruct;
use Jv\Cms\DataResolver\Element\CrossRoomSectionStruct;
use Jv\Cms\DataResolver\Element\EditorialTeamGridStruct;
use Jv\Cms\DataResolver\Element\EditorialTeamMemberMediaStruct;
use Jv\Cms\DataResolver\Element\EditorialTeamMemberStruct;
use Jv\Cms\DataResolver\Element\ExpertProfileLinkStruct;
use Jv\Cms\DataResolver\Element\ExpertProfileMediaStruct;
use Jv\Cms\DataResolver\Element\ExpertProfileStruct;
use Jv\Cms\DataResolver\Element\ExpertQuoteStruct;
use Jv\Cms\DataResolver\Element\ExpertTipStruct;
use Jv\Cms\DataResolver\Element\GuideHubCardMediaStruct;
use Jv\Cms\DataResolver\Element\GuideHubCardsStruct;
use Jv\Cms\DataResolver\Element\GuideHubCardStruct;
use Jv\Cms\DataResolver\Element\InlineProductTeaserLinkStruct;
use Jv\Cms\DataResolver\Element\InlineProductTeaserMediaStruct;
use Jv\Cms\DataResolver\Element\InlineProductTeaserStruct;
use Jv\Cms\DataResolver\Element\InstagramStyleLinkStruct;
use Jv\Cms\DataResolver\Element\InstagramStyleMediaStruct;
use Jv\Cms\DataResolver\Element\InstagramStyleStruct;
use Jv\Cms\DataResolver\Element\LookSceneLinkStruct;
use Jv\Cms\DataResolver\Element\LookSceneMediaStruct;
use Jv\Cms\DataResolver\Element\LookSceneProductStruct;
use Jv\Cms\DataResolver\Element\LookSceneStruct;
use Jv\Cms\DataResolver\Element\LoyaltyPromoBenefitStruct;
use Jv\Cms\DataResolver\Element\LoyaltyPromoLinkStruct;
use Jv\Cms\DataResolver\Element\LoyaltyPromoMediaStruct;
use Jv\Cms\DataResolver\Element\LoyaltyPromoStruct;
use Jv\Cms\DataResolver\Element\PromoDealTileLinkStruct;
use Jv\Cms\DataResolver\Element\PromoDealTileMediaStruct;
use Jv\Cms\DataResolver\Element\PromoDealTilesStruct;
use Jv\Cms\DataResolver\Element\PromoDealTileStruct;
use Jv\Cms\DataResolver\Element\RelatedLookCardMediaStruct;
use Jv\Cms\DataResolver\Element\RelatedLookCardsStruct;
use Jv\Cms\DataResolver\Element\RelatedLookCardStruct;
use Jv\Cms\DataResolver\Element\ReviewSummaryStruct;
use Jv\Cms\DataResolver\Element\SubcategoryLinksItemStruct;
use Jv\Cms\DataResolver\Element\SubcategoryLinksStruct;
use Jv\Cms\DataResolver\Element\TableOfContentsItemStruct;
use Jv\Cms\DataResolver\Element\TableOfContentsStruct;
use Jv\Cms\DataResolver\Element\TrendLookGridCardMediaStruct;
use Jv\Cms\DataResolver\Element\TrendLookGridCardStruct;
use Jv\Cms\DataResolver\Element\TrendLookGridStruct;
use Jv\Cms\DataResolver\Element\TrustRatingLinkStruct;
use Jv\Cms\DataResolver\Element\TrustRatingStruct;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;

final class ExportStoreApiContractsTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testExportHappyPathContractsToJsonFiles(): void
    {
        /** @var StructEncoder $encoder */
        $encoder = static::getContainer()->get(StructEncoder::class);
        $responseFields = new ResponseFields();

        $outDir = $this->resolveOutputDirectory();

        if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
            self::fail(sprintf('Cannot create output directory: %s', $outDir));
        }

        $components = $this->components();
        $index = [];

        foreach ($components as $fileName => $component) {
            /** @var array<string, mixed> $data */
            $data = $encoder->encode($component['struct'], $responseFields);

            $payload = [
                'type' => $component['type'],
                'slot' => 'content',
                'data' => $data,
            ];

            $path = sprintf('%s/%s.json', $outDir, $fileName);
            file_put_contents(
                $path,
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
            );

            $index[] = [
                'file' => basename($path),
                'type' => $component['type'],
                'apiAlias' => $data['apiAlias'] ?? null,
            ];
        }

        file_put_contents(
            sprintf('%s/_index.json', $outDir),
            json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );

        self::assertCount(23, $index);
    }

    private function resolveOutputDirectory(): string
    {
        $contractOutDir = getenv('CMS_CONTRACT_OUT_DIR');

        if (is_string($contractOutDir) && '' !== $contractOutDir) {
            return $contractOutDir;
        }

        return dirname(__DIR__, 5).'/var/cms-store-api-contracts';
    }

    /**
     * @return array<string, array{type: string, struct: object}>
     */
    private function components(): array
    {
        $color = new ColorWorldPickerColorStruct(
            id: 'sand',
            position: 0,
            name: 'Sand',
            url: '/farben/sand',
            image: new ColorWorldPickerColorMediaStruct('/media/sand.webp', 'Sand'),
        );
        $color->assign(['hex' => '#E8DCC8']);

        return [
            'jv-countdown-promo' => [
                'type' => 'jv-countdown-promo',
                'struct' => new CountdownPromoStruct(
                    title: 'Homie Days',
                    eyebrow: 'Nur bis Sonntag',
                    description: 'Bis zu 40 % auf Sofas',
                    endsAt: '2026-12-31T23:59:59+01:00',
                    promoCode: 'HOMIE40',
                    link: new CountdownPromoLinkStruct('Jetzt shoppen', '/sale', 'medium'),
                ),
            ],
            'jv-promo-deal-tiles' => [
                'type' => 'jv-promo-deal-tiles',
                'struct' => new PromoDealTilesStruct(
                    title: 'Deals',
                    eyebrow: 'Diese Woche',
                    tiles: [
                        new PromoDealTileStruct(
                            id: 'sofas',
                            position: 0,
                            label: 'Sofas',
                            description: 'Bis -40 %',
                            discountLabel: '-40 %',
                            endsAt: '2026-09-14T23:59:59+02:00',
                            url: '/sale/sofas',
                            image: new PromoDealTileMediaStruct('/media/deal-sofas.webp', 'Sofas'),
                            link: new PromoDealTileLinkStruct('Entdecken', '/sale/sofas', 'medium'),
                        ),
                    ],
                ),
            ],
            'jv-related-look-cards' => [
                'type' => 'jv-related-look-cards',
                'struct' => new RelatedLookCardsStruct(
                    title: 'Verwandte Looks',
                    cards: [
                        new RelatedLookCardStruct(
                            id: 'skandi',
                            position: 0,
                            title: 'Skandinavisch',
                            description: 'Helle Töne',
                            url: '/looks/skandi',
                            image: new RelatedLookCardMediaStruct('/media/skandi.webp', 'Skandinavisch'),
                        ),
                    ],
                ),
            ],
            'jv-chip-rail' => [
                'type' => 'jv-chip-rail',
                'struct' => new ChipRailStruct(
                    title: 'Stile',
                    eyebrow: 'Entdecken',
                    chips: [
                        new ChipRailChipStruct('sofas', 0, 'Sofas', '/sofas'),
                        new ChipRailChipStruct('betten', 1, 'Betten', '/betten'),
                    ],
                ),
            ],
            'jv-trend-look-grid' => [
                'type' => 'jv-trend-look-grid',
                'struct' => new TrendLookGridStruct(
                    title: 'Trends',
                    eyebrow: 'Inspiration',
                    cards: [
                        new TrendLookGridCardStruct(
                            id: 'warm',
                            position: 0,
                            title: 'Warm minimalism',
                            description: 'Erdige Töne',
                            url: '/trends/warm',
                            image: new TrendLookGridCardMediaStruct('/media/trend-warm.webp', 'Warm minimalism'),
                        ),
                    ],
                ),
            ],
            'jv-look-scene' => [
                'type' => 'jv-look-scene',
                'struct' => new LookSceneStruct(
                    title: 'Wohnzimmer',
                    description: 'Kuratiert',
                    image: new LookSceneMediaStruct('/media/living-scene.webp', 'Wohnzimmer'),
                    products: [
                        new LookSceneProductStruct('sofa', 0, 'Alba Sofa', '/produkt/sofa'),
                    ],
                    viewAll: new LookSceneLinkStruct('Alle ansehen', '/living'),
                ),
            ],
            'jv-color-world-picker' => [
                'type' => 'jv-color-world-picker',
                'struct' => new ColorWorldPickerStruct(
                    title: 'Farben entdecken',
                    description: 'Warme Töne',
                    colors: [$color],
                ),
            ],
            'jv-article-hero' => [
                'type' => 'jv-article-hero',
                'struct' => new ArticleHeroStruct(
                    title: 'Einrichten mit Stil',
                    eyebrow: 'Ratgeber',
                    description: 'Tipps für Zuhause',
                    publishedAt: '2026-03-01T10:00:00+01:00',
                    readTimeMinutes: 5,
                    image: new ArticleHeroMediaStruct('/media/article-hero.webp', 'Einrichten mit Stil'),
                ),
            ],
            'jv-table-of-contents' => [
                'type' => 'jv-table-of-contents',
                'struct' => new TableOfContentsStruct(
                    title: 'Inhalt',
                    items: [
                        new TableOfContentsItemStruct('intro', 0, 'Einleitung', 'einleitung'),
                        new TableOfContentsItemStruct('tipps', 1, 'Tipps', 'tipps'),
                    ],
                ),
            ],
            'jv-expert-tip' => [
                'type' => 'jv-expert-tip',
                'struct' => new ExpertTipStruct(
                    label: 'Tipp',
                    title: 'Licht',
                    body: 'Nutzen Sie warmes Licht am Abend.',
                ),
            ],
            'jv-expert-quote' => [
                'type' => 'jv-expert-quote',
                'struct' => new ExpertQuoteStruct(
                    quote: 'Qualität zahlt sich aus.',
                    authorName: 'Anna',
                    authorRole: 'Interior Expert',
                ),
            ],
            'jv-expert-profile' => [
                'type' => 'jv-expert-profile',
                'struct' => new ExpertProfileStruct(
                    name: 'Anna Müller',
                    role: 'Einrichtungsexpertin',
                    bio: '15 Jahre Erfahrung.',
                    image: new ExpertProfileMediaStruct('/media/expert-anna.webp', 'Anna Müller'),
                    link: new ExpertProfileLinkStruct('Mehr erfahren', '/experten/anna'),
                ),
            ],
            'jv-author-footer' => [
                'type' => 'jv-author-footer',
                'struct' => new AuthorFooterStruct(
                    authorName: 'Anna M.',
                    expertise: 'Einrichtung',
                    bio: 'Redaktion JVMöbel.',
                    image: new AuthorFooterMediaStruct('/media/author-anna.webp', 'Anna M.'),
                    link: new AuthorFooterLinkStruct('Profil', '/autor/anna'),
                ),
            ],
            'jv-guide-hub-cards' => [
                'type' => 'jv-guide-hub-cards',
                'struct' => new GuideHubCardsStruct(
                    title: 'Ratgeber',
                    eyebrow: 'Guides',
                    cards: [
                        new GuideHubCardStruct(
                            id: 'living',
                            position: 0,
                            title: 'Wohnzimmer',
                            description: 'Einrichten leicht gemacht',
                            url: '/guides/living',
                            image: new GuideHubCardMediaStruct('/media/guide-living.webp', 'Wohnzimmer'),
                        ),
                    ],
                ),
            ],
            'jv-editorial-team-grid' => [
                'type' => 'jv-editorial-team-grid',
                'struct' => new EditorialTeamGridStruct(
                    title: 'Unser Team',
                    members: [
                        new EditorialTeamMemberStruct(
                            id: 'anna',
                            position: 0,
                            name: 'Anna Müller',
                            role: 'Redaktion',
                            url: '/team/anna',
                            image: new EditorialTeamMemberMediaStruct('/media/team-anna.webp', 'Anna Müller'),
                        ),
                    ],
                ),
            ],
            'jv-inline-product-teaser' => [
                'type' => 'jv-inline-product-teaser',
                'struct' => new InlineProductTeaserStruct(
                    productId: '019fef2fb9e87af48c6596097b5c43c1',
                    name: 'Noma Chair',
                    description: 'Bouclé',
                    image: new InlineProductTeaserMediaStruct('/media/noma.webp', 'Noma Chair'),
                    link: new InlineProductTeaserLinkStruct('Noma Chair', '/product/noma'),
                ),
            ],
            'jv-instagram-style' => [
                'type' => 'jv-instagram-style',
                'struct' => new InstagramStyleStruct(
                    handle: '@jvmoebel',
                    caption: 'Neues aus dem Showroom',
                    image: new InstagramStyleMediaStruct('/media/instagram.webp', 'Showroom'),
                    link: new InstagramStyleLinkStruct('Folgen', 'https://instagram.com/jvmoebel'),
                ),
            ],
            'jv-trust-rating' => [
                'type' => 'jv-trust-rating',
                'struct' => new TrustRatingStruct(
                    rating: 4.8,
                    reviewCount: 12500,
                    providerLabel: 'Trusted Shops',
                    link: new TrustRatingLinkStruct('Bewertungen', 'https://trustedshops.de'),
                ),
            ],
            'jv-app-download-promo' => [
                'type' => 'jv-app-download-promo',
                'struct' => new AppDownloadPromoStruct(
                    title: 'App herunterladen',
                    description: '10 € Gutschein in der App',
                    appStoreUrl: 'https://apps.apple.com/de/app/example',
                    playStoreUrl: 'https://play.google.com/store/apps/details?id=example',
                    qrImage: new AppDownloadPromoMediaStruct('/media/app-qr.webp', 'QR code'),
                    promoCode: 'APP10',
                ),
            ],
            'jv-loyalty-promo' => [
                'type' => 'jv-loyalty-promo',
                'struct' => new LoyaltyPromoStruct(
                    title: 'Homie Club',
                    description: 'Punkte sammeln',
                    benefits: [
                        new LoyaltyPromoBenefitStruct('shipping', 0, 'Gratis Versand'),
                    ],
                    promoCode: 'CLUB',
                    image: new LoyaltyPromoMediaStruct('/media/loyalty.webp', 'Homie Club'),
                    link: new LoyaltyPromoLinkStruct('Mehr erfahren', '/club', 'medium'),
                ),
            ],
            'jv-review-summary' => [
                'type' => 'jv-review-summary',
                'struct' => new ReviewSummaryStruct(
                    summary: 'Sehr zufrieden',
                    sourceLabel: 'Kundenstimmen',
                    rating: 4.7,
                ),
            ],
            'jv-subcategory-links' => [
                'type' => 'jv-subcategory-links',
                'struct' => new SubcategoryLinksStruct(
                    title: 'Unterkategorien',
                    links: [
                        new SubcategoryLinksItemStruct('ecksofas', 0, 'Ecksofas', '/sofas/eck'),
                    ],
                ),
            ],
            'jv-cross-room-section' => [
                'type' => 'jv-cross-room-section',
                'struct' => new CrossRoomSectionStruct(
                    title: 'Räume entdecken',
                    eyebrow: 'Shop by room',
                    rooms: [
                        new CrossRoomSectionRoomStruct(
                            id: 'living',
                            position: 0,
                            label: 'Wohnzimmer',
                            title: 'Sofas & Tische',
                            description: 'Gemütlich wohnen',
                            url: '/wohnzimmer',
                            image: new CrossRoomSectionRoomMediaStruct('/media/room-living.webp', 'Wohnzimmer'),
                        ),
                    ],
                ),
            ],
        ];
    }
}
