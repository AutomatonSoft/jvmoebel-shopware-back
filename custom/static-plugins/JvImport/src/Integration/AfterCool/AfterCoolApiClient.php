<?php declare(strict_types=1);

namespace Jv\Import\Integration\AfterCool;

use Jv\Import\Integration\AfterCool\Dto\AfterCoolFactory;
use Jv\Import\Integration\AfterCool\Dto\AfterCoolProductPage;
use Jv\Import\Integration\AfterCool\Exception\AfterCoolApiException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class AfterCoolApiClient implements AfterCoolApiClientInterface, Contract\AfterCoolProductPageReaderInterface
{
    private ?string $sessionCookie = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly AfterCoolResponseNormalizer $normalizer,
        private readonly string $baseUri,
        private readonly string $username,
        private readonly string $password,
        private readonly float $timeout,
    ) {
    }

    /** @return list<AfterCoolFactory> */
    public function getFactories(): array
    {
        $context = ['operation' => 'aftercool_api_factories', 'account' => 'JV', 'dataset' => 'lister'];
        $this->logger->info('AfterCool factories request started.', $context);
        $factories = $this->normalizer->normalizeFactories($this->request('GET', '/api/import/factories', ['account' => 'JV', 'dataset' => 'lister']));
        $this->logger->info('AfterCool factories request completed.', [...$context, 'factoriesCount' => count($factories)]);

        return $factories;
    }

    public function getProductPage(int $factoryId, int $offset, int $limit = 100, ?string $query = null): AfterCoolProductPage
    {
        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Aftercool product page limit must be between 1 and 100.');
        }
        $context = ['operation' => 'aftercool_api_products_page', 'account' => 'JV', 'dataset' => 'lister', 'factoryId' => $factoryId, 'offset' => $offset, 'limit' => $limit];
        $this->logger->info('AfterCool product page request started.', $context);
        $parameters = ['account' => 'JV', 'dataset' => 'lister', 'factory_id' => $factoryId, 'limit' => $limit, 'offset' => $offset, 'include_row' => 1];
        if (null !== $query && '' !== trim($query)) {
            $parameters['q'] = trim($query);
        }
        $page = $this->normalizer->normalizeProductPage($this->request('GET', '/api/products', $parameters), 'JV', 'lister', $factoryId, $offset, $limit);
        $this->logger->info('AfterCool product page request completed.', [...$context, 'itemsCount' => count($page->items), 'hasMore' => $page->hasMore]);

        return $page;
    }

    /** @param array<string, scalar> $query
     * @return array<string, mixed>|list<mixed>
     */
    private function request(string $method, string $path, array $query): array
    {
        $this->authenticate();
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            $response = $this->send($method, $path, ['query' => $query]);
            if (401 === $response->getStatusCode() && 0 === $attempt) {
                $this->sessionCookie = null;
                $this->authenticate();
                continue;
            }

            return $this->payload($response);
        }
        throw new \LogicException('The AfterCool request loop unexpectedly finished.');
    }

    private function authenticate(): void
    {
        if (null !== $this->sessionCookie) {
            return;
        }
        $response = $this->send('POST', '/auth/login', ['json' => ['username' => $this->username, 'password' => $this->password]]);
        if (200 !== $response->getStatusCode()) {
            throw AfterCoolApiException::authenticationFailed();
        }
        foreach ($response->getHeaders(false)['set-cookie'] ?? [] as $cookie) {
            if ('' !== trim($cookie)) {
                $this->sessionCookie = trim(explode(';', $cookie, 2)[0]);

                return;
            }
        }
        throw AfterCoolApiException::authenticationFailed();
    }

    /** @param array<string, mixed> $options */
    private function send(string $method, string $path, array $options): ResponseInterface
    {
        $options['timeout'] = $this->timeout;
        if (null !== $this->sessionCookie && 'POST' !== $method) {
            $options['headers'] = ['Cookie' => $this->sessionCookie];
        }
        try {
            return $this->httpClient->request($method, rtrim($this->baseUri, '/').$path, $options);
        } catch (TransportExceptionInterface $exception) {
            throw AfterCoolApiException::transport($exception);
        }
    }

    /** @return array<string, mixed>|list<mixed> */
    private function payload(ResponseInterface $response): array
    {
        $status = $response->getStatusCode();
        if (200 !== $status) {
            if (401 === $status) {
                throw AfterCoolApiException::authenticationFailed();
            } throw AfterCoolApiException::http($status);
        }
        try {
            return $response->toArray(false);
        } catch (DecodingExceptionInterface $exception) {
            throw AfterCoolApiException::invalidJson($exception);
        } catch (TransportExceptionInterface $exception) {
            throw AfterCoolApiException::transport($exception);
        }
    }
}
