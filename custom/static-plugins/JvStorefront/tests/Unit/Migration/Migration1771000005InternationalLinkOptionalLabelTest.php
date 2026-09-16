<?php declare(strict_types=1);

namespace Jv\Storefront\Tests\Unit\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Types;
use Jv\Storefront\Migration\Migration1771000005InternationalLinkOptionalLabel;
use PHPUnit\Framework\TestCase;

final class Migration1771000005InternationalLinkOptionalLabelTest extends TestCase
{
    public function testItSkipsWhenTableIsMissing(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager
            ->expects(self::once())
            ->method('tablesExist')
            ->with(['jv_storefront_international_link'])
            ->willReturn(false);
        $schemaManager
            ->expects(self::never())
            ->method('listTableColumns');

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('createSchemaManager')
            ->willReturn($schemaManager);
        $connection
            ->expects(self::never())
            ->method('executeStatement');

        (new Migration1771000005InternationalLinkOptionalLabel())->update($connection);
    }

    public function testItAddsMissingLabelColumnAsNullable(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager
            ->expects(self::once())
            ->method('tablesExist')
            ->with(['jv_storefront_international_link'])
            ->willReturn(true);
        $schemaManager
            ->expects(self::once())
            ->method('listTableColumns')
            ->with('jv_storefront_international_link')
            ->willReturn([]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('createSchemaManager')
            ->willReturn($schemaManager);
        $connection
            ->expects(self::once())
            ->method('executeStatement')
            ->with(
                'ALTER TABLE `jv_storefront_international_link` ADD `label` VARCHAR(255) NULL AFTER `target_sales_channel_id`'
            );

        (new Migration1771000005InternationalLinkOptionalLabel())->update($connection);
    }

    public function testItSkipsWhenLabelIsAlreadyNullable(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager
            ->expects(self::once())
            ->method('tablesExist')
            ->with(['jv_storefront_international_link'])
            ->willReturn(true);
        $schemaManager
            ->expects(self::once())
            ->method('listTableColumns')
            ->with('jv_storefront_international_link')
            ->willReturn([
                'label' => new Column('label', \Doctrine\DBAL\Types\Type::getType(Types::STRING), ['notnull' => false]),
            ]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('createSchemaManager')
            ->willReturn($schemaManager);
        $connection
            ->expects(self::never())
            ->method('executeStatement');

        (new Migration1771000005InternationalLinkOptionalLabel())->update($connection);
    }

    public function testItMakesLabelNullableWhenColumnIsNotNullable(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager
            ->expects(self::once())
            ->method('tablesExist')
            ->with(['jv_storefront_international_link'])
            ->willReturn(true);
        $schemaManager
            ->expects(self::once())
            ->method('listTableColumns')
            ->with('jv_storefront_international_link')
            ->willReturn([
                'label' => new Column('label', \Doctrine\DBAL\Types\Type::getType(Types::STRING), ['notnull' => true]),
            ]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('createSchemaManager')
            ->willReturn($schemaManager);
        $connection
            ->expects(self::once())
            ->method('executeStatement')
            ->with(
                "ALTER TABLE `jv_storefront_international_link`\n                MODIFY `label` VARCHAR(255) NULL"
            );

        (new Migration1771000005InternationalLinkOptionalLabel())->update($connection);
    }
}
