<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\Okb;

use Jv\Import\Integration\Okb\OkbProductApiClient;
use Jv\Import\Integration\Okb\OkbProductResponseNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class OkbProductApiClientTest extends TestCase
{
    public function testItRetriesATemporaryOkbFailure(): void
    {
        $failed = $this->createMock(ResponseInterface::class);
        $failed->expects(self::once())->method('getStatusCode')->willReturn(500);
        $successful = $this->createMock(ResponseInterface::class);
        $successful->expects(self::once())->method('getStatusCode')->willReturn(200);
        $successful->expects(self::once())->method('toArray')->with(false)->willReturn(['productVariations' => [[
            'productReference' => '4260454042902', 'sku' => '4260454042902', 'ean' => '4260454042902',
            'productDescription' => ['category' => 'Sofas', 'attributes' => []],
        ]]]);
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::exactly(2))->method('request')->willReturnOnConsecutiveCalls($failed, $successful);

        $variation = (new OkbProductApiClient($httpClient, new OkbProductResponseNormalizer(), 'https://okb.example'))->findByEan('4260454042902');

        self::assertSame('4260454042902', $variation->ean);
    }

    public function testItReportsTheEanWhenOkbReturnsAnHttpError(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::exactly(3))->method('getStatusCode')->willReturn(500);
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::exactly(3))->method('request')->with(
            'GET',
            'https://okb.example/extermal/get_products',
            ['query' => ['sku' => '4260454042902'], 'timeout' => 20],
        )->willReturn($response);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OKB lookup for EAN "4260454042902" returned HTTP 500.');

        (new OkbProductApiClient($httpClient, new OkbProductResponseNormalizer(), 'https://okb.example'))->findByEan('4260454042902');
    }
}
