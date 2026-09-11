<?php declare(strict_types=1);

namespace Jv\Import\Service\OrderImport;

use Jv\Import\Service\OrderImport\Dto\CosmoShopOrderReferences;
use Jv\Import\Service\OrderImport\Exception\CosmoShopOrderConfigurationException;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;

final readonly class CosmoShopOrderReferenceResolver
{
    /** @param EntityRepository<SalesChannelCollection> $salesChannels
     * @param EntityRepository<SalutationCollection>        $salutations
     * @param EntityRepository<CountryCollection>           $countries
     * @param EntityRepository<StateMachineStateCollection> $states
     */
    public function __construct(private EntityRepository $salesChannels, private EntityRepository $salutations, private EntityRepository $countries, private EntityRepository $states)
    {
    }

    public function resolve(Market $market, Context $context): CosmoShopOrderReferences
    {
        $salesChannel = $this->salesChannels->search(new Criteria([$market->salesChannelId()]), $context)->first();
        if (!$salesChannel instanceof SalesChannelEntity) {
            throw new CosmoShopOrderConfigurationException('Market sales channel is unavailable.');
        }
        $countryIds = [];
        foreach ($this->countries->search(new Criteria(), $context) as $country) {
            if (null !== $country->getIso()) {
                $countryIds[strtoupper($country->getIso())] = $country->getId();
            }
        }
        $states = [];
        $criteria = (new Criteria())->addAssociation('stateMachine');
        foreach ($this->states->search($criteria, $context) as $state) {
            $machine = $state->getStateMachine();
            if (null !== $machine) {
                $states[$machine->getTechnicalName().'.'.$state->getTechnicalName()] = $state->getId();
            }
        }
        $salutations = [];
        foreach ($this->salutations->search(new Criteria(), $context) as $salutation) {
            $salutations[(string) $salutation->get('salutationKey')] = $salutation->getUniqueIdentifier();
        }

        return new CosmoShopOrderReferences($salesChannel, $countryIds, $states, $salutations);
    }
}
