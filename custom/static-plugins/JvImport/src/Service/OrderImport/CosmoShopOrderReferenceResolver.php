<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport;

use Doctrine\DBAL\Connection;
use Jv\Import\Service\OrderImport\Dto\CosmoShopOrderReferences;
use Jv\Import\Service\OrderImport\Exception\CosmoShopOrderConfigurationException;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Salutation\SalutationCollection;

final readonly class CosmoShopOrderReferenceResolver
{
    /** @param EntityRepository<SalesChannelCollection> $salesChannels
     * @param EntityRepository<SalutationCollection> $salutations
     */
    public function __construct(private EntityRepository $salesChannels, private EntityRepository $salutations, private Connection $connection)
    {
    }

    public function resolve(Market $market, Context $context): CosmoShopOrderReferences
    {
        $salesChannel = $this->salesChannels->search(new Criteria([$market->salesChannelId()]), $context)->first();
        if (!$salesChannel instanceof SalesChannelEntity) {
            throw new CosmoShopOrderConfigurationException('Market sales channel is unavailable.');
        }
        $countryIds = array_flip($this->connection->fetchAllKeyValue('SELECT LOWER(HEX(id)), iso FROM country'));
        $states = [];
        foreach ($this->connection->fetchAllAssociative('SELECT LOWER(HEX(s.id)) id, s.technical_name name, m.technical_name machine_name FROM state_machine_state s INNER JOIN state_machine m ON m.id=s.state_machine_id WHERE (m.technical_name = ? AND s.technical_name IN (?, ?, ?)) OR (m.technical_name = ? AND s.technical_name IN (?, ?)) OR (m.technical_name = ? AND s.technical_name IN (?, ?))', ['order.state', 'in_progress', 'open', 'completed', 'order_transaction.state', 'paid', 'open', 'order_delivery.state', 'open', 'shipped']) as $row) {
            $states[$row['machine_name'].'.'.$row['name']] = $row['id'];
        }
        $salutations = [];
        foreach ($this->salutations->search(new Criteria(), $context) as $salutation) {
            $salutations[(string) $salutation->get('salutationKey')] = $salutation->getUniqueIdentifier();
        }

        return new CosmoShopOrderReferences($salesChannel, $countryIds, $states, $salutations);
    }
}
