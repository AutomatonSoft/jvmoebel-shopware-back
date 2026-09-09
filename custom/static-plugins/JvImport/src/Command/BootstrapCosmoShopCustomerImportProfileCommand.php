<?php declare(strict_types=1);

namespace Jv\Import\Command;

use Jv\Import\Integration\CosmoShop\Profile\CustomerImportProfile;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'jv:cosmoshop:bootstrap-customer-import-profile',
    description: 'Creates or updates one market-specific CosmoShop customer import profile.',
)]
final class BootstrapCosmoShopCustomerImportProfileCommand extends Command
{
    private const string NEWSLETTER_PROFILE_TECHNICAL_NAME = 'default_newsletter_recipient';

    /**
     * @param EntityRepository<EntityCollection<ImportExportProfileEntity>> $profileRepository
     * @param EntityRepository<SalesChannelCollection>                      $salesChannelRepository
     */
    public function __construct(
        private readonly EntityRepository $profileRepository,
        private readonly EntityRepository $salesChannelRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('market', InputArgument::REQUIRED, 'CosmoShop market domain.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $market = Market::tryFrom((string) $input->getArgument('market'));
        if (null === $market) {
            throw new \InvalidArgumentException('A known CosmoShop market domain is required.');
        }

        $context = Context::createCLIContext();
        $salesChannel = $this->salesChannelRepository->search(new Criteria([$market->salesChannelId()]), $context)->first();
        if (!$salesChannel instanceof SalesChannelEntity) {
            throw new \InvalidArgumentException('The market sales channel does not exist.');
        }

        $newsletterProfile = $this->profileRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', self::NEWSLETTER_PROFILE_TECHNICAL_NAME)),
            $context,
        )->first();
        if (!$newsletterProfile instanceof ImportExportProfileEntity
            || !in_array($newsletterProfile->getType(), [
                ImportExportProfileEntity::TYPE_IMPORT,
                ImportExportProfileEntity::TYPE_IMPORT_EXPORT,
            ], true)
            || 'newsletter_recipient' !== $newsletterProfile->getSourceEntity()
        ) {
            throw new \RuntimeException('The required newsletter recipient import profile is unavailable.');
        }

        $this->profileRepository->upsert([
            CustomerImportProfile::definition($market, $salesChannel->getCustomerGroupId()),
            [
                'id' => $newsletterProfile->getId(),
                'config' => [...$newsletterProfile->getConfig(), 'escape' => ''],
            ],
        ], $context);

        $output->writeln('Customer and newsletter import profiles configured.');

        return self::SUCCESS;
    }
}
