<?php declare(strict_types=1);

namespace Jv\Import\Controller;

use Jv\Import\Core\Content\AfterCoolImportError\AfterCoolImportErrorCollection;
use Jv\Import\Core\Content\AfterCoolImportError\AfterCoolImportErrorEntity;
use Jv\Import\Core\Content\AfterCoolImportRun\AfterCoolImportRunCollection;
use Jv\Import\Core\Content\AfterCoolImportRun\AfterCoolImportRunEntity;
use Jv\Import\Integration\AfterCool\AfterCoolApiClientInterface;
use Jv\Import\Integration\AfterCool\Exception\AfterCoolApiException;
use Jv\Import\Integration\AfterCool\Exception\AfterCoolResponseContractException;
use Jv\Import\Service\AfterCool\Contract\AfterCoolProductPreviewProviderInterface;
use Jv\Import\Service\AfterCool\Exception\AfterCoolFactoryImportAlreadyRunningException;
use Jv\Import\Service\AfterCool\Exception\AfterCoolFactoryNotFoundException;
use Jv\Import\Service\AfterCool\StartAfterCoolImportService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api'], '_acl' => ['system.import_export']])]
final class AfterCoolImportController extends AbstractController
{
    /**
     * @param EntityRepository<AfterCoolImportRunCollection>   $runRepository
     * @param EntityRepository<AfterCoolImportErrorCollection> $errorRepository
     */
    public function __construct(
        private readonly AfterCoolApiClientInterface $api,
        private readonly StartAfterCoolImportService $startImport,
        private readonly EntityRepository $runRepository,
        private readonly EntityRepository $errorRepository,
        private readonly AfterCoolProductPreviewProviderInterface $preview,
    ) {
    }

    #[Route(path: '/api/_action/jv-import/aftercool/products', name: 'api.action.jv_import.aftercool.products', methods: ['GET'])]
    public function products(Request $request): JsonResponse
    {
        $factoryId = $request->query->getInt('factoryId');
        $limit = min(50, max(1, $request->query->getInt('limit', 25)));
        $offset = max(0, $request->query->getInt('offset', 0));
        if ($factoryId < 1) {
            return new JsonResponse(['errors' => [['code' => 'invalid_factory_id', 'detail' => 'factoryId must be a positive integer.']]], Response::HTTP_BAD_REQUEST);
        }

        $query = $request->query->getString('q');

        try {
            $data = $this->preview->preview($factoryId, $limit, $offset, '' === $query ? null : $query);
        } catch (AfterCoolApiException|AfterCoolResponseContractException $exception) {
            return new JsonResponse(['errors' => [['code' => $exception instanceof AfterCoolApiException ? $exception->safeCode() : 'aftercool_invalid_response', 'detail' => 'Aftercool products could not be loaded.']]], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['data' => $data['items'], 'total' => $data['total'], 'limit' => $data['limit'], 'offset' => $data['offset'], 'hasMore' => $data['hasMore']]);
    }

    #[Route(path: '/api/_action/jv-import/aftercool/factories', name: 'api.action.jv_import.aftercool.factories', methods: ['GET'])]
    public function factories(): JsonResponse
    {
        return new JsonResponse(['data' => array_map(static fn ($factory): array => [
            'id' => $factory->id,
            'name' => $factory->name,
        ], $this->api->getFactories())]);
    }

    #[Route(path: '/api/_action/jv-import/aftercool/runs', name: 'api.action.jv_import.aftercool.run.create', methods: ['POST'])]
    public function start(Request $request, Context $context): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $factoryId = is_array($payload) ? ($payload['factoryId'] ?? null) : null;
        if (!is_int($factoryId)) {
            return new JsonResponse(['errors' => [['code' => 'invalid_factory_id', 'detail' => 'factoryId must be an integer.']]], Response::HTTP_BAD_REQUEST);
        }

        try {
            $runId = $this->startImport->start($factoryId, $context);
        } catch (AfterCoolFactoryNotFoundException) {
            return new JsonResponse(['errors' => [['code' => 'factory_not_found', 'detail' => 'Aftercool factory was not found.']]], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (AfterCoolFactoryImportAlreadyRunningException) {
            return new JsonResponse(['errors' => [['code' => 'factory_import_already_running', 'detail' => 'An import for this factory is already active.']]], Response::HTTP_CONFLICT);
        }

        return new JsonResponse(['data' => ['id' => $runId]], Response::HTTP_ACCEPTED);
    }

    #[Route(path: '/api/_action/jv-import/aftercool/runs/{runId}', name: 'api.action.jv_import.aftercool.run.get', methods: ['GET'])]
    public function run(string $runId, Context $context): JsonResponse
    {
        $run = $this->runRepository->search(new Criteria([$runId]), $context)->first();
        if (!$run instanceof AfterCoolImportRunEntity) {
            return new JsonResponse(['errors' => [['code' => 'run_not_found', 'detail' => 'Aftercool import run was not found.']]], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['data' => [
            'id' => $run->getId(),
            'factoryId' => $run->getFactoryId(),
            'factoryName' => $run->getFactoryName(),
            'status' => $run->getStatus(),
            'total' => $run->getTotal(),
            'nextOffset' => $run->getNextOffset(),
            'processed' => $run->getProcessed(),
            'created' => $run->getCreated(),
            'updated' => $run->getUpdated(),
            'skipped' => $run->getSkipped(),
            'failed' => $run->getFailed(),
            'failureCode' => $run->getSafeFailureCode(),
            'failureMessage' => $run->getSafeFailureMessage(),
        ]]);
    }

    #[Route(path: '/api/_action/jv-import/aftercool/runs/{runId}/errors', name: 'api.action.jv_import.aftercool.run.errors', methods: ['GET'])]
    public function errors(string $runId, Request $request, Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->setLimit(min(100, max(1, $request->query->getInt('limit', 50))));
        $criteria->setOffset(max(0, $request->query->getInt('offset', 0)));
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter('runId', $runId));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('id', FieldSorting::ASCENDING));
        $result = $this->errorRepository->search($criteria, $context);

        return new JsonResponse(['data' => array_values(array_map(static fn (AfterCoolImportErrorEntity $error): array => [
            'id' => $error->getId(),
            'factoryId' => $error->getFactoryId(),
            'productId' => $error->getProductId(),
            'artikelnummer' => $error->getArtikelnummer(),
            'ean' => $error->getEan(),
            'offset' => $error->getOffset(),
            'rowNo' => $error->getRowNo(),
            'result' => $error->getResult(),
            'code' => $error->getCode(),
            'message' => $error->getMessage(),
        ], $result->getElements())), 'total' => $result->getTotal()]);
    }
}
