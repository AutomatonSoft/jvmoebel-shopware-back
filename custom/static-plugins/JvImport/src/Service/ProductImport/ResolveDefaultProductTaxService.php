<?php declare(strict_types=1);

namespace Jv\Import\Service\ProductImport;

use Jv\Import\Service\ProductImport\Dto\ResolvedProductTax;
use Jv\Import\Service\ProductImport\Exception\DefaultProductTaxException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\Tax\TaxCollection;

final class ResolveDefaultProductTaxService
{
    private ?ResolvedProductTax $resolvedTax = null;

    /** @param EntityRepository<TaxCollection> $taxRepository */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $taxRepository,
    ) {
    }

    public function execute(): ResolvedProductTax
    {
        if (null !== $this->resolvedTax) {
            return $this->resolvedTax;
        }

        $taxId = $this->systemConfigService->get('core.tax.defaultTaxRate');
        if (!is_string($taxId) || !Uuid::isValid($taxId)) {
            throw new DefaultProductTaxException('Shopware default tax is not configured.');
        }

        $tax = $this->taxRepository->search(new Criteria([$taxId]), Context::createDefaultContext())->first();
        if (null === $tax) {
            throw new DefaultProductTaxException('Configured Shopware default tax does not exist.');
        }

        return $this->resolvedTax = new ResolvedProductTax($tax->getId(), $tax->getTaxRate());
    }
}
