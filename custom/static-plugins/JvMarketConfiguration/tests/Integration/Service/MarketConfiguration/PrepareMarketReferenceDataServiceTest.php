<?php declare(strict_types=1);

namespace Jv\MarketConfiguration\Tests\Integration\Service\MarketConfiguration;

use Jv\MarketConfiguration\Service\MarketConfiguration\Dto\MarketDefinition;
use Jv\MarketConfiguration\Service\MarketConfiguration\PrepareMarketReferenceDataService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;

final class PrepareMarketReferenceDataServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testEnsureLanguageReactivatesInactiveLanguage(): void
    {
        $context = Context::createDefaultContext();

        /** @var EntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = static::getContainer()->get('language.repository');
        $languageId = $languageRepository->searchIds(
            (new Criteria())
                ->setLimit(1)
                ->addFilter(new EqualsFilter('locale.code', 'en-GB')),
            $context,
        )->firstId();
        self::assertNotNull($languageId);

        $languageRepository->update([[
            'id' => $languageId,
            'active' => false,
        ]], $context);
        self::assertFalse($this->language($languageRepository, $languageId, $context)->isActive());

        $service = static::getContainer()->get(PrepareMarketReferenceDataService::class);
        self::assertInstanceOf(PrepareMarketReferenceDataService::class, $service);

        $prepared = $service->execute([
            new MarketDefinition('jvfurniture.co.uk', 'en-GB', 'GBP', 'GB'),
        ], $context);

        self::assertSame($languageId, $prepared->languageId('en-GB'));
        self::assertTrue($this->language($languageRepository, $languageId, $context)->isActive());
    }

    /**
     * @param EntityRepository<LanguageCollection> $repository
     */
    private function language(EntityRepository $repository, string $id, Context $context): LanguageEntity
    {
        $language = $repository->search(new Criteria([$id]), $context)->first();
        self::assertInstanceOf(LanguageEntity::class, $language);

        return $language;
    }
}
