<?php

declare(strict_types=1);

namespace KimiNexus\Integrations\BusinessGovtNz;

use GuzzleHttp\ClientInterface;
use KimiNexus\Core\ApiConfig;

final class BusinessGovtNzApiClient
{
    /** @var ClientInterface */
    private $httpClient;

    /** @var ApiConfig */
    private $config;

    public function __construct(ClientInterface $httpClient, ApiConfig $config)
    {
        $this->httpClient = $httpClient;
        $this->config = $config;
    }

    /**
     * Hello World 示例接口。
     * 不发起真实 HTTP 请求，直接返回固定内容。
     *
     * @param string $path
     * @return array<string, mixed>
     */
    public function helloWorld(string $path = '/helloworld'): array
    {
        return [
            'status_code' => 200,
            'data' => [
                'message' => 'helloworld',
                'path' => $path,
            ],
            'raw_body' => 'helloworld',
        ];
    }
}
