<?php declare(strict_types=1);

namespace Jv\ProductOptions\Tests\Unit\Service\OptionPricing;

use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateGroup\OptionTemplateGroupCollection;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateGroup\OptionTemplateGroupEntity;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValue\OptionTemplateValueCollection;
use Jv\ProductOptions\Core\Content\OptionTemplate\Aggregate\OptionTemplateValue\OptionTemplateValueEntity;
use Jv\ProductOptions\Core\Content\OptionTemplate\OptionTemplateEntity;
use Jv\ProductOptions\Service\OptionPricing\Exception\InvalidOptionSelectionException;
use Jv\ProductOptions\Service\OptionPricing\OptionSelectionResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(OptionSelectionResolver::class)]
final class OptionSelectionResolverTest extends TestCase
{
    private const TEMPLATE = '01950000000070008000000000000000';
    private const MATERIAL = '01950000000070008000000000000001';
    private const COLOR = '01950000000070008000000000000002';
    private const FABRIC = '01950000000070008000000000000011';
    private const LEATHER = '01950000000070008000000000000012';
    private const WHITE = '01950000000070008000000000000021';
    private const BLACK = '01950000000070008000000000000022';
    private const FOREIGN_VALUE = '01950000000070008000000000000099';
    private const FOREIGN_GROUP = '01950000000070008000000000000088';

    private OptionSelectionResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new OptionSelectionResolver();
    }

    public function testNullSelectionAppliesAllDefaults(): void
    {
        $resolved = $this->resolver->resolve($this->template(), null);

        self::assertSame([self::FABRIC, self::WHITE], array_map(static fn (OptionTemplateValueEntity $v): string => $v->getId(), $resolved));
    }

    public function testEmptySelectionAppliesAllDefaults(): void
    {
        $resolved = $this->resolver->resolve($this->template(), []);

        self::assertSame([self::FABRIC, self::WHITE], array_map(static fn (OptionTemplateValueEntity $v): string => $v->getId(), $resolved));
    }

    public function testExplicitSelectionOverridesDefault(): void
    {
        $resolved = $this->resolver->resolve($this->template(), [
            self::MATERIAL => self::LEATHER,
            self::COLOR => self::BLACK,
        ]);

        self::assertSame([self::LEATHER, self::BLACK], array_map(static fn (OptionTemplateValueEntity $v): string => $v->getId(), $resolved));
    }

    public function testGroupWithoutDefaultRequiresExplicitSelection(): void
    {
        $template = $this->template(withColorDefault: false);

        $this->expectException(InvalidOptionSelectionException::class);
        $this->resolver->resolve($template, [self::MATERIAL => self::LEATHER]);
    }

    #[DataProvider('invalidSelectionProvider')]
    public function testInvalidSelectionIsRejected(mixed $selections): void
    {
        $this->expectException(InvalidOptionSelectionException::class);

        $this->resolver->resolve($this->template(), $selections);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidSelectionProvider(): iterable
    {
        yield 'list instead of map' => [[self::LEATHER]];
        yield 'scalar payload' => ['string'];
        yield 'invalid group uuid' => [['not-a-uuid' => self::LEATHER]];
        yield 'invalid value uuid' => [[self::MATERIAL => 'not-a-uuid']];
        yield 'value from another group' => [[self::MATERIAL => self::WHITE]];
        yield 'unknown value uuid' => [[self::MATERIAL => self::FOREIGN_VALUE]];
        yield 'unknown group uuid' => [[self::FOREIGN_GROUP => self::FABRIC]];
    }

    public function testEmptyTemplateAllowsOnlyEmptySelection(): void
    {
        $emptyTemplate = new OptionTemplateEntity();
        $emptyTemplate->setId(self::TEMPLATE);
        $emptyTemplate->setGroups(new OptionTemplateGroupCollection());

        self::assertSame([], $this->resolver->resolve($emptyTemplate, null));
        self::assertSame([], $this->resolver->resolve($emptyTemplate, []));

        $this->expectException(InvalidOptionSelectionException::class);
        $this->resolver->resolve($emptyTemplate, [self::MATERIAL => self::FABRIC]);
    }

    private function template(bool $withColorDefault = true): OptionTemplateEntity
    {
        $template = new OptionTemplateEntity();
        $template->setId(self::TEMPLATE);

        $material = new OptionTemplateGroupEntity();
        $material->setId(self::MATERIAL);
        $material->setTemplateId(self::TEMPLATE);
        $material->setPosition(1);
        $material->setDefaultValueId(self::FABRIC);

        $fabric = new OptionTemplateValueEntity();
        $fabric->setId(self::FABRIC);
        $fabric->setGroupId(self::MATERIAL);
        $fabric->setPosition(1);

        $leather = new OptionTemplateValueEntity();
        $leather->setId(self::LEATHER);
        $leather->setGroupId(self::MATERIAL);
        $leather->setPosition(2);

        $material->setValues(new OptionTemplateValueCollection([$fabric, $leather]));

        $color = new OptionTemplateGroupEntity();
        $color->setId(self::COLOR);
        $color->setTemplateId(self::TEMPLATE);
        $color->setPosition(2);
        if ($withColorDefault) {
            $color->setDefaultValueId(self::WHITE);
        }

        $white = new OptionTemplateValueEntity();
        $white->setId(self::WHITE);
        $white->setGroupId(self::COLOR);
        $white->setPosition(1);

        $black = new OptionTemplateValueEntity();
        $black->setId(self::BLACK);
        $black->setGroupId(self::COLOR);
        $black->setPosition(2);

        $color->setValues(new OptionTemplateValueCollection([$white, $black]));

        $template->setGroups(new OptionTemplateGroupCollection([$material, $color]));

        return $template;
    }
}
