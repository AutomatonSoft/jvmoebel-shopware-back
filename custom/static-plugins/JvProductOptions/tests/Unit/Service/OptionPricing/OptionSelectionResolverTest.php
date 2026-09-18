<?php declare(strict_types=1);

namespace Jv\ProductOptions\Tests\Unit\Service\OptionPricing;

use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateGroup\OptionTemplateGroupCollection;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateGroup\OptionTemplateGroupEntity;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValue\OptionTemplateValueCollection;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValue\OptionTemplateValueEntity;
use Jv\ProductOptions\Core\Content\OptionTemplate\OptionTemplateEntity;
use Jv\ProductOptions\Service\OptionPricing\Exception\InvalidOptionSelectionException;
use Jv\ProductOptions\Service\OptionPricing\OptionSelectionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

final class OptionSelectionResolverTest extends TestCase
{
    private const MATERIAL = '0190a0a0a0a07000800000000000a001';
    private const FABRIC = '0190a0a0a0a07000800000000000b001';
    private const LEATHER = '0190a0a0a0a07000800000000000b002';
    private const COLOR = '0190a0a0a0a07000800000000000a002';
    private const GREY = '0190a0a0a0a07000800000000000c001';
    private const RED = '0190a0a0a0a07000800000000000c002';

    private OptionSelectionResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new OptionSelectionResolver();
    }

    public function testMissingSelectionUsesDefaults(): void
    {
        $values = $this->resolver->resolve($this->template(), null);

        self::assertSame([self::FABRIC, self::GREY], $this->ids($values));
    }

    public function testSelectedValuesReplaceDefaultsInGroupOrder(): void
    {
        $values = $this->resolver->resolve($this->template(), [self::COLOR => self::RED, self::MATERIAL => self::LEATHER]);

        self::assertSame([self::LEATHER, self::RED], $this->ids($values));
    }

    public function testPartialSelectionFillsOtherGroupsWithDefaults(): void
    {
        $values = $this->resolver->resolve($this->template(), [self::COLOR => self::RED]);

        self::assertSame([self::FABRIC, self::RED], $this->ids($values));
    }

    public function testGroupWithoutDefaultMustBeSelected(): void
    {
        $template = $this->template(colorDefault: null);

        self::assertSame([self::FABRIC, self::RED], $this->ids($this->resolver->resolve($template, [self::COLOR => self::RED])));

        $this->expectException(InvalidOptionSelectionException::class);
        $this->resolver->resolve($template, [self::MATERIAL => self::LEATHER]);
    }

    /**
     * @param mixed $selections
     */
    #[DataProvider('invalidSelectionProvider')]
    public function testInvalidSelectionIsRejected($selections): void
    {
        $this->expectException(InvalidOptionSelectionException::class);

        $this->resolver->resolve($this->template(), $selections);
    }

    /** @return iterable<string, array{0: mixed}> */
    public static function invalidSelectionProvider(): iterable
    {
        yield 'not an object' => ['leather'];
        yield 'list instead of map' => [[self::LEATHER]];
        yield 'foreign group' => [[Uuid::randomHex() => self::LEATHER]];
        yield 'value of another group' => [[self::MATERIAL => self::RED]];
        yield 'unknown value' => [[self::MATERIAL => Uuid::randomHex()]];
        yield 'value is not a uuid' => [[self::MATERIAL => 'leather']];
        yield 'value is not a string' => [[self::MATERIAL => 42]];
    }

    private function template(?string $colorDefault = self::GREY): OptionTemplateEntity
    {
        $template = new OptionTemplateEntity();
        $template->setId(Uuid::randomHex());
        $template->setGroups(new OptionTemplateGroupCollection([
            $this->group(self::COLOR, 2, $colorDefault, [self::GREY, self::RED]),
            $this->group(self::MATERIAL, 1, self::FABRIC, [self::FABRIC, self::LEATHER]),
        ]));

        return $template;
    }

    /**
     * @param list<string> $valueIds
     */
    private function group(string $id, int $position, ?string $defaultValueId, array $valueIds): OptionTemplateGroupEntity
    {
        $values = new OptionTemplateValueCollection();
        foreach ($valueIds as $index => $valueId) {
            $value = new OptionTemplateValueEntity();
            $value->setId($valueId);
            $value->setGroupId($id);
            $value->setPosition($index + 1);
            $values->add($value);
        }

        $group = new OptionTemplateGroupEntity();
        $group->setId($id);
        $group->setPosition($position);
        $group->setDefaultValueId($defaultValueId);
        $group->setValues($values);

        return $group;
    }

    /**
     * @param list<OptionTemplateValueEntity> $values
     *
     * @return list<string>
     */
    private function ids(array $values): array
    {
        return array_map(static fn (OptionTemplateValueEntity $value): string => $value->getId(), $values);
    }
}
