<?php declare(strict_types=1);

namespace Jv\Import\Integration\Okb;

use Jv\Import\Integration\Okb\Dto\OkbProductVariation;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OkbProductApiClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private OkbProductResponseNormalizer $normalizer,
        private string $baseUri,
    ) {
    }

    public function findByEan(string $ean): OkbProductVariation
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($this->baseUri, '/').'/extermal/get_products', [
                'query' => ['sku' => $ean],
                'timeout' => 20,
            ]);
            $status = $response->getStatusCode();
            if (200 !== $status) {
                throw new \RuntimeException(sprintf('OKB lookup for EAN "%s" returned HTTP %d.', $ean, $status));
            }
            $payload = $response->toArray(false);

            return $this->normalizer->normalize($ean, $payload);
        } catch (TransportExceptionInterface $exception) {
            throw new \RuntimeException(sprintf('OKB lookup for EAN "%s" failed because the service is unavailable.', $ean), previous: $exception);
        }
    }
}
