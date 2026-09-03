<?php declare(strict_types=1);

namespace Jv\Import\Service\AfterCool;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class AfterCoolMediaStageService
{
    public function __construct(private Connection $connection)
    {
    }

    /** @param list<string> $urls */
    public function stage(string $runId, int $offset, string $sourceProductId, string $productId, array $urls, bool $hasCover): void
    {
        foreach ($urls as $position => $url) {
            $this->connection->executeStatement(
                <<<'SQL'
                    INSERT INTO `jv_aftercool_media_stage`
                        (`id`, `run_id`, `offset`, `source_product_id`, `product_id`, `url`, `url_hash`, `position`, `cover_candidate`, `status`, `created_at`)
                    VALUES (:id, :runId, :offset, :sourceProductId, :productId, :url, :urlHash, :position, :coverCandidate, 'pending', NOW(3))
                    ON DUPLICATE KEY UPDATE `updated_at` = NOW(3)
                    SQL,
                [
                    'id' => Uuid::fromHexToBytes(Uuid::fromStringToHex('jvmoebel.aftercool.media-stage.'.$runId.'.'.$offset.'.'.$productId.'.'.$url)),
                    'runId' => Uuid::fromHexToBytes($runId),
                    'offset' => $offset,
                    'sourceProductId' => $sourceProductId,
                    'productId' => Uuid::fromHexToBytes($productId),
                    'url' => $url,
                    'urlHash' => hash('sha256', $url),
                    'position' => $position,
                    'coverCandidate' => 0 === $position && !$hasCover ? 1 : 0,
                ],
                [
                    'id' => ParameterType::BINARY,
                    'runId' => ParameterType::BINARY,
                    'productId' => ParameterType::BINARY,
                ],
            );
        }
    }
}
