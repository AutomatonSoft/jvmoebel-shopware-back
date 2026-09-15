<?php declare(strict_types=1);

namespace Jv\Import\Tests\Unit\Integration\Okb;

use Jv\Import\Integration\Okb\Dto\OkbProductVariation;
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

    public function testItFetchesTheWholeFamilyByProductReference(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('getStatusCode')->willReturn(200);
        $response->expects(self::once())->method('toArray')->with(false)->willReturn(['productVariations' => [
            $this->payload('4260454042902'),
            $this->payload('4260454042903'),
        ]]);
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::once())->method('request')->with(
            'GET',
            'https://okb.example/extermal/get_products',
            ['query' => ['productReference' => '4260454042902', 'page' => 0, 'limit' => 200], 'timeout' => 20],
        )->willReturn($response);

        $family = (new OkbProductApiClient($httpClient, new OkbProductResponseNormalizer(), 'https://okb.example'))->findFamily('4260454042902');

        self::assertSame(['4260454042902', '4260454042903'], array_map(static fn (OkbProductVariation $v): string => $v->ean, $family));
    }

    public function testItKeepsPagingWhileOkbFillsThePage(): void
    {
        $full = $this->createMock(ResponseInterface::class);
        $full->expects(self::once())->method('getStatusCode')->willReturn(200);
        $full->expects(self::once())->method('toArray')->with(false)->willReturn([
            'productVariations' => array_map(fn (int $i): array => $this->payload(sprintf('42604540%05d', $i)), range(1, 200)),
        ]);
        $tail = $this->createMock(ResponseInterface::class);
        $tail->expects(self::once())->method('getStatusCode')->willReturn(200);
        $tail->expects(self::once())->method('toArray')->with(false)->willReturn(['productVariations' => [$this->payload('4260454099999')]]);
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::exactly(2))->method('request')->willReturnOnConsecutiveCalls($full, $tail);

        $family = (new OkbProductApiClient($httpClient, new OkbProductResponseNormalizer(), 'https://okb.example'))->findFamily('4260454000001');

        self::assertCount(201, $family);
    }

    public function testAnEmptyFamilyIsNotAnError(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('getStatusCode')->willReturn(200);
        $response->expects(self::once())->method('toArray')->with(false)->willReturn(['productVariations' => []]);
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::once())->method('request')->willReturn($response);

        self::assertSame([], (new OkbProductApiClient($httpClient, new OkbProductResponseNormalizer(), 'https://okb.example'))->findFamily('4260454000001'));
    }

    /** @return array<string, mixed> */
    private function payload(string $ean): array
    {
        return [
            'productReference' => '4260454042902',
            'sku' => $ean,
            'ean' => $ean,
            'productDescription' => ['category' => 'Sofas', 'attributes' => []],
        ];
    }
}
