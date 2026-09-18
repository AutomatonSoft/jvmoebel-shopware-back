<?php declare(strict_types=1);

namespace Jv\Seo\Service\Redirect;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;

final readonly class LandingPageTargetUrlResolver
{
    public function __construct(private Connection $connection)
    {
    }

    public function resolve(string $landingPageId, string $salesChannelId, ?string $sourceUrl = null): ?string
    {
        /** @var list<array{seoPathInfo: string, domainUrl: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT su.seo_path_info AS seoPathInfo, scd.url AS domainUrl
            FROM seo_url su
            INNER JOIN sales_channel sc ON sc.id = su.sales_channel_id
            INNER JOIN sales_channel_domain scd
                ON scd.sales_channel_id = sc.id
                AND scd.language_id = su.language_id
            WHERE su.route_name = 'frontend.landing.page'
                AND su.foreign_key = :landingPageId
                AND su.sales_channel_id = :salesChannelId
                AND su.is_canonical = 1
                AND su.is_deleted = 0
            ORDER BY (scd.language_id = sc.language_id) DESC, scd.url ASC
        SQL, [
            'landingPageId' => Uuid::fromHexToBytes($landingPageId),
            'salesChannelId' => Uuid::fromHexToBytes($salesChannelId),
        ]);

        if ([] === $rows) {
            return null;
        }

        if (null !== $sourceUrl) {
            $sourceHost = strtolower((string) parse_url($sourceUrl, PHP_URL_HOST));
            foreach ($rows as $row) {
                if ($sourceHost === strtolower((string) parse_url($row['domainUrl'], PHP_URL_HOST))) {
                    return rtrim($row['domainUrl'], '/').'/'.ltrim($row['seoPathInfo'], '/');
                }
            }
        }

        return rtrim($rows[0]['domainUrl'], '/').'/'.ltrim($rows[0]['seoPathInfo'], '/');
    }
}
