<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\AfterCool;

use Jv\Import\Integration\AfterCool\AfterCoolApiClient;
use Jv\Import\Integration\AfterCool\AfterCoolResponseNormalizer;
use Jv\Import\Integration\AfterCool\Exception\AfterCoolApiException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class AfterCoolApiClientTest extends TestCase
{
    public function testItAuthenticatesOnceAndUsesTheSessionForFactoriesAndProductPages(): void
    {
        $responses = [
            $this->response(200, [], ['set-cookie' => ['aftercool_session=session-one; Path=/; HttpOnly']]),
            $this->response(200, $this->fixture('factories.json')),
            $this->response(200, $this->fixture('products-page-0.json')),
        ];
        $calls = [];
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::exactly(3))->method('request')->willReturnCallback(
            static function (string $method, string $url, array $options) use (&$calls, &$responses): ResponseInterface {
                $calls[] = [$method, $url, $options];

                return array_shift($responses) ?? throw new \LogicException('Unexpected Aftercool request.');
            },
        );
        $client = $this->client($httpClient);

        $factories = $client->getFactories();
        $page = $client->getProductPage('504034', 0);

        self::assertCount(2, $factories);
        self::assertSame('504034', $factories[0]->id);
        self::assertSame('NEW_Person_046_Nurai', $factories[0]->name);
        self::assertSame('504000', $factories[1]->id, 'Factory identity must not be derived from a non-unique name.');
        self::assertSame(102, $page->total);
        self::assertTrue($page->hasMore);

        self::assertSame('POST', $calls[0][0]);
        self::assertSame('https://aftercool.example/auth/login', $calls[0][1]);
        self::assertSame(['username' => 'api-user', 'password' => 'api-password'], $calls[0][2]['json'] ?? null);
        self::assertSame(12.5, $calls[0][2]['timeout'] ?? null);

        self::assertSame('GET', $calls[1][0]);
        self::assertSame('https://aftercool.example/api/import/factories', $calls[1][1]);
        self::assertSame(['account' => 'JV', 'dataset' => 'lister'], $calls[1][2]['query'] ?? null);
        self::assertSame('aftercool_session=session-one', $this->cookieHeader($calls[1][2]));

        self::assertSame('GET', $calls[2][0]);
        self::assertSame('https://aftercool.example/api/products', $calls[2][1]);
        self::assertSame([
            'account' => 'JV',
            'dataset' => 'lister',
            'factory_id' => '504034',
            'limit' => 100,
            'offset' => 0,
            'include_row' => 1,
        ], $calls[2][2]['query'] ?? null);
        self::assertSame('aftercool_session=session-one', $this->cookieHeader($calls[2][2]));
    }

    public function testItReauthenticatesOnlyOnceAfterAnUnauthorizedResponse(): void
    {
        $responses = [
            $this->response(200, [], ['set-cookie' => ['aftercool_session=expired; Path=/']]),
            $this->response(401),
            $this->response(200, [], ['set-cookie' => ['aftercool_session=fresh; Path=/']]),
            $this->response(200, $this->fixture('products-page-100.json')),
        ];
        $calls = [];
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::exactly(4))->method('request')->willReturnCallback(
            static function (string $method, string $url, array $options) use (&$calls, &$responses): ResponseInterface {
                $calls[] = [$method, $url, $options];

                return array_shift($responses) ?? throw new \LogicException('Unexpected Aftercool request.');
            },
        );

        $page = $this->client($httpClient)->getProductPage('504034', 100);

        self::assertFalse($page->hasMore);
        self::assertSame(['POST', 'GET', 'POST', 'GET'], array_column($calls, 0));
        self::assertSame('aftercool_session=expired', $this->cookieHeader($calls[1][2]));
        self::assertSame('aftercool_session=fresh', $this->cookieHeader($calls[3][2]));
    }

    public function testItDoesNotEnterAnAuthenticationLoopAfterASecondUnauthorizedResponse(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::exactly(4))->method('request')->willReturnOnConsecutiveCalls(
            $this->response(200, [], ['set-cookie' => ['aftercool_session=first; Path=/']]),
            $this->response(401),
            $this->response(200, [], ['set-cookie' => ['aftercool_session=second; Path=/']]),
            $this->response(401),
        );

        try {
            $this->client($httpClient)->getFactories();
            self::fail('A second HTTP 401 must fail the current import run.');
        } catch (AfterCoolApiException $exception) {
            self::assertFalse($exception->isRetryable());
            self::assertSame('aftercool_authentication_failed', $exception->safeCode());
        }
    }

    #[DataProvider('httpFailureProvider')]
    public function testItClassifiesHttpFailuresForMessengerRetry(int $status, bool $retryable, string $safeCode): void
    {
        $rawBody = 'raw-body-containing-api-password-and-private-source-data';
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::exactly(2))->method('request')->willReturnOnConsecutiveCalls(
            $this->response(200, [], ['set-cookie' => ['aftercool_session=session; Path=/']]),
            $this->response($status, ['raw' => $rawBody]),
        );

        try {
            $this->client($httpClient)->getFactories();
            self::fail(sprintf('HTTP %d must not be accepted.', $status));
        } catch (AfterCoolApiException $exception) {
            self::assertSame($retryable, $exception->isRetryable());
            self::assertSame($safeCode, $exception->safeCode());
            self::assertStringNotContainsString('api-password', $exception->getMessage());
            self::assertStringNotContainsString($rawBody, $exception->getMessage());
        }
    }

    /** @return iterable<string, array{int, bool, string}> */
    public static function httpFailureProvider(): iterable
    {
        yield 'rate limited' => [429, true, 'aftercool_http_429'];
        yield 'internal server error' => [500, true, 'aftercool_http_500'];
        yield 'server error' => [503, true, 'aftercool_http_503'];
        yield 'invalid factory' => [422, false, 'aftercool_http_422'];
        yield 'forbidden' => [403, false, 'aftercool_http_403'];
    }

    public function testItClassifiesATransportFailureAsRetryableWithoutLeakingItsDetails(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::exactly(2))->method('request')->willReturnOnConsecutiveCalls(
            $this->response(200, [], ['set-cookie' => ['aftercool_session=session; Path=/']]),
            self::throwException(new class('private network details') extends \RuntimeException implements TransportExceptionInterface {}),
        );

        try {
            $this->client($httpClient)->getFactories();
            self::fail('A transport failure must be handed to Messenger retry.');
        } catch (AfterCoolApiException $exception) {
            self::assertTrue($exception->isRetryable());
            self::assertSame('aftercool_transport_error', $exception->safeCode());
            self::assertStringNotContainsString('private network details', $exception->getMessage());
        }
    }

    public function testMalformedJsonIsAPermanentSafeContractFailure(): void
    {
        $malformed = $this->createMock(ResponseInterface::class);
        $malformed->method('getStatusCode')->willReturn(200);
        $malformed->method('toArray')->with(false)->willThrowException(
            new class('raw malformed response') extends \RuntimeException implements DecodingExceptionInterface {},
        );
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::exactly(2))->method('request')->willReturnOnConsecutiveCalls(
            $this->response(200, [], ['set-cookie' => ['aftercool_session=session; Path=/']]),
            $malformed,
        );

        try {
            $this->client($httpClient)->getFactories();
            self::fail('Malformed JSON must fail the run instead of being treated as an empty factory list.');
        } catch (AfterCoolApiException $exception) {
            self::assertFalse($exception->isRetryable());
            self::assertSame('aftercool_invalid_json', $exception->safeCode());
            self::assertStringNotContainsString('raw malformed response', $exception->getMessage());
        }
    }

    private function client(HttpClientInterface $httpClient): AfterCoolApiClient
    {
        return new AfterCoolApiClient(
            $httpClient,
            new AfterCoolResponseNormalizer(),
            'https://aftercool.example/',
            'api-user',
            'api-password',
            12.5,
        );
    }

    /** @param array<string, mixed> $payload
     * @param array<string, list<string>> $headers
     */
    private function response(int $status, array $payload = [], array $headers = []): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getHeaders')->with(false)->willReturn($headers);
        $response->method('toArray')->with(false)->willReturn($payload);

        return $response;
    }

    /** @return array<mixed> */
    private function fixture(string $name): array
    {
        $contents = file_get_contents(__DIR__.'/../../../Fixtures/AfterCool/'.$name);
        self::assertIsString($contents);

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $options */
    private function cookieHeader(array $options): ?string
    {
        $headers = $options['headers'] ?? [];
        self::assertIsArray($headers);
        foreach ($headers as $name => $value) {
            if (is_string($name) && 'cookie' === strtolower($name)) {
                return is_array($value) ? ($value[0] ?? null) : $value;
            }
        }

        return null;
    }
}
