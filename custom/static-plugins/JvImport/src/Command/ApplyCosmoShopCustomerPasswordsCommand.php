<?php declare(strict_types=1);

namespace Jv\Import\Command;

use Jv\Import\Integration\CosmoShop\Customer\CosmoShopCustomerIdentity;
use Jv\MarketConfiguration\Service\MarketConfiguration\Market;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'jv:cosmoshop:apply-customer-passwords',
    description: 'Applies imported CosmoShop customer password material without exposing it.',
)]
final class ApplyCosmoShopCustomerPasswordsCommand extends Command
{
    /** @param EntityRepository<CustomerCollection> $customerRepository */
    public function __construct(private readonly EntityRepository $customerRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('market', InputArgument::REQUIRED, 'CosmoShop market domain.');
        $this->addArgument('file', InputArgument::REQUIRED, 'UTF-8 password CSV file.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate input without persisting passwords.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $market = Market::tryFrom((string) $input->getArgument('market'));
        $file = (string) $input->getArgument('file');
        if (null === $market) {
            throw new \InvalidArgumentException('A known CosmoShop market domain is required.');
        }
        if (!is_file($file) || !is_readable($file)) {
            throw new \InvalidArgumentException('The password CSV file cannot be read.');
        }

        $stream = fopen($file, 'rb');
        if (false === $stream) {
            throw new \InvalidArgumentException('The password CSV file cannot be read.');
        }

        $counts = ['processed' => 0, 'legacy' => 0, 'rehash' => 0, 'reset_required' => 0, 'missing_customer' => 0, 'failed' => 0];
        $context = Context::createCLIContext();
        $dryRun = (bool) $input->getOption('dry-run');

        try {
            $header = fgetcsv($stream, separator: ';', enclosure: '"', escape: '\\');
            if ($header !== ['source_customer_id', 'password_hash', 'salt']) {
                throw new \InvalidArgumentException('The password CSV header is invalid.');
            }

            while (($record = fgetcsv($stream, separator: ';', enclosure: '"', escape: '\\')) !== false) {
                ++$counts['processed'];
                if (3 !== count($record) || !ctype_digit($record[0])) {
                    ++$counts['failed'];
                    continue;
                }

                $customerId = CosmoShopCustomerIdentity::customerId($market, (int) $record[0]);
                if (null === $this->customerRepository->searchIds(new Criteria([$customerId]), $context)->firstId()) {
                    ++$counts['missing_customer'];
                    continue;
                }

                $password = $record[1];
                $salt = $record[2];
                if ('' === $password || $this->isResetRequired($password, $salt)) {
                    ++$counts['reset_required'];
                    continue;
                }

                if (str_starts_with($password, 's512##')) {
                    if (!preg_match('/^s512##[A-Za-z0-9+\\/]{86}$/', $password) || 1 !== preg_match('/^[A-Za-z0-9]{32}$/', $salt)) {
                        ++$counts['failed'];
                        continue;
                    }
                    ++$counts['legacy'];
                    if (!$dryRun) {
                        $this->customerRepository->update([[
                            'id' => $customerId,
                            'legacyPassword' => $password.':'.$salt,
                            'legacyEncoder' => 'CosmoShopS512',
                        ]], $context);
                    }
                    continue;
                }

                if ('' !== $salt) {
                    ++$counts['failed'];
                    continue;
                }

                ++$counts['rehash'];
                if (!$dryRun) {
                    $this->customerRepository->update([[
                        'id' => $customerId,
                        'password' => password_hash($password, \PASSWORD_DEFAULT),
                        'legacyPassword' => null,
                        'legacyEncoder' => null,
                    ]], $context);
                }
            }
        } finally {
            fclose($stream);
        }

        $output->writeln(implode(' ', array_map(static fn (string $key, int $value): string => $key.'='.$value, array_keys($counts), $counts)));

        return self::SUCCESS;
    }

    private function isResetRequired(string $password, string $salt): bool
    {
        return ('xx' === mb_strtolower($password) && 'xx' === mb_strtolower($salt))
            || ('' === $salt && in_array(mb_strtolower($password), ['empty', 'empty-password', 'password-empty', 'no-password'], true));
    }
}
