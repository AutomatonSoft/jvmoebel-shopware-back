<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Service\Catalog;

use Doctrine\DBAL\Connection;
use Jv\Import\Service\Catalog\BackfillCatalogPropertyTranslationsService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;

final class BackfillCatalogPropertyTranslationsServiceTest extends TestCase
{
    public function testItAddsOnlyMissingTranslationsForImportedPropertiesAndOptions(): void
    {
        $connection = $this->createMock(Connection::class);
        $statement = 0;
        $connection->expects(self::exactly(2))->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $parameters) use (&$statement): int {
                ++$statement;
                self::assertStringContainsString(1 === $statement ? 'INSERT INTO `property_group_translation`' : 'INSERT INTO `property_group_option_translation`', $sql);
                self::assertStringContainsString(1 === $statement ? '`translation`.`property_group_id` IS NULL' : '`translation`.`property_group_option_id` IS NULL', $sql);
                self::assertStringContainsString('`jv_catalog_category_attribute`', $sql);
                self::assertStringContainsString('`source_translation`.`name`', $sql);
                self::assertSame(['systemLanguageId' => Defaults::LANGUAGE_SYSTEM], $parameters);

                return 4;
            },
        );

        self::assertSame(8, (new BackfillCatalogPropertyTranslationsService($connection))->execute());
    }
}
