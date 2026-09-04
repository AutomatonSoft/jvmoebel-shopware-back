<?php declare(strict_types=1);

namespace Jv\Import\Integration\Okb;

use Jv\Import\Integration\Okb\Dto\OkbProductVariation;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OkbProductApiClient
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private HttpClientInterface $httpClient,
        private OkbProductResponseNormalizer $normalizer,
        private string $baseUri,
    ) {
    }

    public function findByEan(string $ean): OkbProductVariation
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            try {
                $response = $this->httpClient->request('GET', rtrim($this->baseUri, '/').'/extermal/get_products', [
                    'query' => ['sku' => $ean],
                    'timeout' => 20,
                ]);
                $status = $response->getStatusCode();
                if (200 !== $status) {
                    if ($this->isTemporaryStatus($status) && $attempt < self::MAX_ATTEMPTS) {
                        $this->waitBeforeRetry($attempt);

                        continue;
                    }
                    throw new \RuntimeException(sprintf('OKB lookup for EAN "%s" returned HTTP %d.', $ean, $status));
                }
                $payload = $response->toArray(false);

                return $this->normalizer->normalize($ean, $payload);
            } catch (TransportExceptionInterface $exception) {
                if ($attempt < self::MAX_ATTEMPTS) {
                    $this->waitBeforeRetry($attempt);

                    continue;
                }

                throw new \RuntimeException(sprintf('OKB lookup for EAN "%s" failed because the service is unavailable.', $ean), previous: $exception);
            }
        }

        throw new \LogicException('OKB lookup retry loop unexpectedly finished.');
    }

    private function isTemporaryStatus(int $status): bool
    {
        return 429 === $status || 500 <= $status;
    }

    private function waitBeforeRetry(int $attempt): void
    {
        usleep($attempt * 100000);
    }
}
