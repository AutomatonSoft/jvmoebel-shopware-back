<?php declare(strict_types=1);

namespace Jv\Import\Tests\Integration\Migration;

use Doctrine\DBAL\Connection;
use Jv\Import\Integration\CosmoShop\Profile\MarketImportProfile;
use Jv\Import\Migration\Migration1770000017UpdateCosmoShopMediaProfileMapping;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

final class UpdateCosmoShopMediaProfileMappingTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItUpdatesAnExistingProfileToTheCanonicalMediaMapping(): void
    {
        $market = Market::Germany;
        $profile = MarketImportProfile::definition($market);
        $connection = $this->connection();

        $connection->executeStatement(
            'INSERT INTO import_export_profile (id, technical_name, type, source_entity, file_type, delimiter, enclosure, mapping, update_by, config, created_at) VALUES (:id, :technicalName, :type, :sourceEntity, :fileType, :delimiter, :enclosure, :mapping, :updateBy, :config, NOW(3)) ON DUPLICATE KEY UPDATE mapping = VALUES(mapping)',
            [
                'id' => Uuid::fromHexToBytes($profile['id']),
                'technicalName' => $profile['technicalName'],
                'type' => $profile['type'],
                'sourceEntity' => $profile['sourceEntity'],
                'fileType' => $profile['fileType'],
                'delimiter' => $profile['delimiter'],
                'enclosure' => $profile['enclosure'],
                'mapping' => json_encode([['key' => 'productNumber', 'mappedKey' => 'product_number', 'position' => 1]], \JSON_THROW_ON_ERROR),
                'updateBy' => json_encode(['id'], \JSON_THROW_ON_ERROR),
                'config' => json_encode([], \JSON_THROW_ON_ERROR),
            ],
        );

        (new Migration1770000017UpdateCosmoShopMediaProfileMapping())->update($connection);

        self::assertSame(
            MarketImportProfile::mapping($market),
            json_decode((string) $connection->fetchOne('SELECT mapping FROM import_export_profile WHERE technical_name = :technicalName', ['technicalName' => $profile['technicalName']]), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
