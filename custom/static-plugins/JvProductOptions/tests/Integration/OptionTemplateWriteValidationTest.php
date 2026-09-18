<?php declare(strict_types=1);

namespace Jv\ProductOptions\Tests\Integration;

use Jv\ProductOptions\Core\Content\OptionTemplate\OptionTemplateCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;

final class OptionTemplateWriteValidationTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testValidTemplateIsWritten(): void
    {
        $id = Uuid::randomHex();
        $this->repository()->create([$this->template($id, [
            'surchargeType' => 'fixed',
            'surchargePrice' => $this->price(200.0),
            'colorHex' => '#A1b2C3',
        ])], Context::createDefaultContext());

        self::assertSame(1, $this->repository()->searchIds(new Criteria([$id]), Context::createDefaultContext())->getTotal());
    }

    /**
     * @param array<string, mixed> $value
     */
    #[DataProvider('invalidValueProvider')]
    public function testInvalidValueIsRejected(array $value, string $field): void
    {
        try {
            $this->repository()->create([$this->template(Uuid::randomHex(), $value)], Context::createDefaultContext());
            self::fail('Invalid option value was written.');
        } catch (WriteException|WriteConstraintViolationException $exception) {
            self::assertStringContainsString($field, $exception->getMessage().json_encode($exception->getErrors()));
        }
    }

    /** @return iterable<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidValueProvider(): iterable
    {
        $price = [['currencyId' => Defaults::CURRENCY, 'gross' => 10.0, 'net' => 8.4, 'linked' => false]];

        yield 'fixed without price' => [['surchargeType' => 'fixed'], 'surchargePrice'];
        yield 'fixed with percentage' => [['surchargeType' => 'fixed', 'surchargePrice' => $price, 'surchargePercentage' => 10.0], 'surchargePercentage'];
        yield 'fixed with negative price' => [['surchargeType' => 'fixed', 'surchargePrice' => [['currencyId' => Defaults::CURRENCY, 'gross' => -1.0, 'net' => -1.0, 'linked' => false]]], 'surchargePrice'];
        yield 'fixed without default currency' => [['surchargeType' => 'fixed', 'surchargePrice' => [['currencyId' => Uuid::randomHex(), 'gross' => 10.0, 'net' => 8.4, 'linked' => false]]], 'surchargePrice'];
        yield 'percentage without value' => [['surchargeType' => 'percentage'], 'surchargePercentage'];
        yield 'percentage with price' => [['surchargeType' => 'percentage', 'surchargePercentage' => 10.0, 'surchargePrice' => $price], 'surchargePrice'];
        yield 'percentage below zero' => [['surchargeType' => 'percentage', 'surchargePercentage' => -1.0], 'surchargePercentage'];
        yield 'percentage above limit' => [['surchargeType' => 'percentage', 'surchargePercentage' => 1000.01], 'surchargePercentage'];
        yield 'unknown type' => [['surchargeType' => 'discount', 'surchargePercentage' => 10.0], 'surchargeType'];
        yield 'bad color' => [['surchargeType' => 'percentage', 'surchargePercentage' => 10.0, 'colorHex' => 'red'], 'colorHex'];
    }

    public function testDefaultValueMustBelongToSameGroup(): void
    {
        $context = Context::createDefaultContext();
        $foreignValueId = Uuid::randomHex();
        $groupId = Uuid::randomHex();
        $template = $this->template(Uuid::randomHex(), ['surchargeType' => 'percentage', 'surchargePercentage' => 0.0]);
        $template['groups'][0]['id'] = $groupId;
        $template['groups'][] = [
            'name' => 'Other',
            'position' => 2,
            'values' => [['id' => $foreignValueId, 'name' => 'Foreign', 'position' => 1, 'surchargeType' => 'percentage', 'surchargePercentage' => 0.0]],
        ];
        $this->repository()->create([$template], $context);

        try {
            static::getContainer()->get('jv_option_template_group.repository')->update([
                ['id' => $groupId, 'defaultValueId' => $foreignValueId],
            ], $context);
            self::fail('Default value of another group was accepted.');
        } catch (WriteException|WriteConstraintViolationException $exception) {
            self::assertStringContainsString('defaultValueId', $exception->getMessage().json_encode($exception->getErrors()));
        }
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array<string, mixed>
     */
    private function template(string $id, array $value): array
    {
        return [
            'id' => $id,
            'name' => 'Template',
            'groups' => [[
                'name' => 'Material',
                'position' => 1,
                'values' => [array_merge(['name' => 'Value', 'position' => 1], $value)],
            ]],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function price(float $gross): array
    {
        return [['currencyId' => Defaults::CURRENCY, 'gross' => $gross, 'net' => round($gross / 1.19, 2), 'linked' => false]];
    }

    /**
     * @return EntityRepository<OptionTemplateCollection>
     */
    private function repository(): EntityRepository
    {
        $repository = static::getContainer()->get('jv_option_template.repository');
        self::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
