<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport;

use Doctrine\DBAL\Connection;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;

/** Advances only the selected order number range; it never decrements a counter. */
final readonly class CosmoShopOrderNumberRangeSynchronizer
{
    public function __construct(private AbstractIncrementStorage $increments, private Connection $connection)
    {
    }

    /** @param list<string> $written */
    public function synchronize(Market $market, array $written): void
    {
        $numbers = [...$written, ...$this->connection->fetchFirstColumn('SELECT order_number FROM `order` WHERE JSON_EXTRACT(custom_fields, \'$.jv_cosmoshop_historical_import\') = true AND JSON_UNQUOTE(JSON_EXTRACT(custom_fields, \'$.jv_cosmoshop_source_market\')) = ?', [$market->domain()])];
        $numbers = array_map(static fn (string $number): int => (int) $number, array_filter($numbers, static fn (string $number): bool => ctype_digit($number)));
        if ([] === $numbers) {
            return;
        }
        $range = $this->connection->fetchAssociative('SELECT LOWER(HEX(r.id)) AS id, r.pattern, r.start FROM number_range r JOIN number_range_type t ON t.id=r.type_id JOIN number_range_sales_channel n ON n.number_range_id=r.id AND n.sales_channel_id = UNHEX(:salesChannelId) WHERE t.technical_name = :type ORDER BY r.id LIMIT 1', ['type' => 'order', 'salesChannelId' => $market->salesChannelId()]);
        if (!is_array($range)) {
            $range = $this->connection->fetchAssociative('SELECT LOWER(HEX(r.id)) AS id, r.pattern, r.start FROM number_range r JOIN number_range_type t ON t.id=r.type_id WHERE t.technical_name = :type AND r.global = 1 AND NOT EXISTS (SELECT 1 FROM number_range_sales_channel assigned WHERE assigned.number_range_id = r.id AND assigned.sales_channel_id IS NOT NULL) ORDER BY r.id LIMIT 1', ['type' => 'order']);
        }
        if (!is_array($range) || !is_string($range['id'] ?? null)) {
            return;
        }
        $config = ['id' => $range['id'], 'pattern' => (string) ($range['pattern'] ?? '{n}'), 'start' => null === $range['start'] ? null : (int) $range['start']];
        if ($this->increments->preview($config) <= max($numbers)) {
            $this->increments->set($range['id'], max($numbers));
        }
    }
}
