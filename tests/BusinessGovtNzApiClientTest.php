<?php

declare(strict_types=1);

namespace KimiNexus\Tests;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use KimiNexus\Core\ApiConfig;
use KimiNexus\Core\ApiException;
use KimiNexus\Integrations\BusinessGovtNz\BusinessGovtNzApiClient;
use PHPUnit\Framework\TestCase;

final class BusinessGovtNzApiClientTest extends TestCase
{
    public function testSearchEntitiesByNameBuildsQueryAndReturnsDecodedData(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                '/gateway/nzbn/v5/entities',
                [
                    'query' => [
                        'search-term' => 'acme',
                        'entity-status' => 'Registered,InLiquidation',
                        'entity-type' => 'NZCompany,OverseasCompany',
                        'industry-code' => 'M692250',
                        'page' => 0,
                        'page-size' => 20,
                    ],
                ]
            )
            ->willReturn(new Response(200, [], '{"items":[{"nzbn":"9429040000000"}],"total":1}'));

        $client = new BusinessGovtNzApiClient($httpClient, new ApiConfig('https://api.business.govt.nz/'));
        $result = $client->searchEntitiesByName('acme', [
            'entity_status' => ['Registered', 'InLiquidation'],
            'entity_type' => ['NZCompany', 'OverseasCompany'],
            'industry_code' => 'M692250',
            'page' => 0,
            'page_size' => 20,
        ]);

        $this->assertSame(200, $result['status_code']);
        $this->assertSame('acme', $result['query']['search_term']);
        $this->assertSame(1, $result['data']['total']);
        $this->assertSame('9429040000000', $result['data']['items'][0]['nzbn']);
    }

    public function testSearchEntitiesByNameRejectsEmptyKeyword(): void
    {
        $client = new BusinessGovtNzApiClient($this->createMock(ClientInterface::class), new ApiConfig('https://api.business.govt.nz/'));
        $this->expectException(\InvalidArgumentException::class);
        $client->searchEntitiesByName('   ');
    }

    public function testSearchEntitiesByNameThrowsWhenResponseIsNotJson(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->willReturn(new Response(200, [], 'not-json'));

        $client = new BusinessGovtNzApiClient($httpClient, new ApiConfig('https://api.business.govt.nz/'));
        $this->expectException(ApiException::class);
        $client->searchEntitiesByName('acme');
    }

    public function testViewEntityByNzbnBuildsPathAndHeaders(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                '/gateway/nzbn/v5/entities/9429040000000',
                [
                    'headers' => [
                        'api-business-govt-nz-Request-Id' => '2a444f3f-a486-4f7c-9bcf-e02095fd3576',
                        'If-None-Match' => 'W/"abc123"',
                    ],
                ]
            )
            ->willReturn(new Response(200, ['ETag' => 'W/"xyz789"'], '{"nzbn":"9429040000000"}'));

        $client = new BusinessGovtNzApiClient($httpClient, new ApiConfig('https://api.business.govt.nz/'));
        $result = $client->viewEntityByNzbn('9429040000000', [
            'request_id' => '2a444f3f-a486-4f7c-9bcf-e02095fd3576',
            'if_none_match' => 'W/"abc123"',
        ]);

        $this->assertSame(200, $result['status_code']);
        $this->assertSame('9429040000000', $result['query']['nzbn']);
        $this->assertSame('W/"xyz789"', $result['response_headers']['etag']);
        $this->assertSame('9429040000000', $result['data']['nzbn']);
    }

    public function testViewEntityByNzbnRejectsEmptyNzbn(): void
    {
        $client = new BusinessGovtNzApiClient($this->createMock(ClientInterface::class), new ApiConfig('https://api.business.govt.nz/'));
        $this->expectException(\InvalidArgumentException::class);
        $client->viewEntityByNzbn(' ');
    }

    public function testViewEntityByNzbnAcceptsNotModified304(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->willReturn(new Response(304, ['ETag' => 'W/"etag304"'], ''));

        $client = new BusinessGovtNzApiClient($httpClient, new ApiConfig('https://api.business.govt.nz/'));
        $result = $client->viewEntityByNzbn('9429040000000', [
            'if_none_match' => 'W/"oldEtag"',
        ]);

        $this->assertSame(304, $result['status_code']);
        $this->assertTrue($result['not_modified']);
        $this->assertNull($result['data']);
        $this->assertSame('W/"etag304"', $result['response_headers']['etag']);
    }
}
