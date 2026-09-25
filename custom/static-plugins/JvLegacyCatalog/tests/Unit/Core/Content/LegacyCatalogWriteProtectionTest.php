<?php declare(strict_types=1);

namespace Jv\LegacyCatalog\Tests\Unit\Core\Content;

use Jv\LegacyCatalog\Core\Content\Category\LegacyCategoryDefinition;
use Jv\LegacyCatalog\Core\Content\CategoryContent\LegacyCategoryContentDefinition;
use Jv\LegacyCatalog\Core\Content\Source\LegacyCatalogSourceDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;

final class LegacyCatalogWriteProtectionTest extends TestCase
{
    #[DataProvider('definitions')]
    public function testArchiveEntitiesCanOnlyBeWrittenInSystemScope(EntityDefinition $definition): void
    {
        $protection = $definition->getProtections()->get(WriteProtection::class);

        self::assertInstanceOf(WriteProtection::class, $protection);
        self::assertTrue($protection->isAllowed(Context::SYSTEM_SCOPE));
        self::assertFalse($protection->isAllowed(Context::USER_SCOPE));
    }

    public function testSourceTextFieldsRetainHtmlAndEmptyStrings(): void
    {
        $definition = new LegacyCategoryContentDefinition();
        $fields = (new \ReflectionMethod($definition, 'defineFields'))->invoke($definition);

        foreach (['rubnam', 'rubtext', 'rubtextKurz', 'urlkey', 'pageTitle', 'metaDescription', 'metaKeywords'] as $property) {
            $field = null;
            foreach ($fields as $candidate) {
                if ($candidate->getPropertyName() === $property) {
                    $field = $candidate;

                    break;
                }
            }

            self::assertNotNull($field, sprintf('The source field "%s" must be defined.', $property));
            self::assertInstanceOf(LongTextField::class, $field);
            self::assertFalse($field->getFlag(AllowHtml::class)->isSanitized());
            self::assertTrue($field->is(AllowEmptyString::class));
        }
    }

    /** @return iterable<string, array{EntityDefinition}> */
    public static function definitions(): iterable
    {
        yield 'source' => [new LegacyCatalogSourceDefinition()];
        yield 'category' => [new LegacyCategoryDefinition()];
        yield 'category content' => [new LegacyCategoryContentDefinition()];
    }
}
