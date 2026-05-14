<?php

declare(strict_types=1);

namespace KimiNexus\Integrations\BusinessGovtNz;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use KimiNexus\Core\ApiConfig;
use KimiNexus\Core\ApiException;

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
     * Hello World 连通性接口。
     * 默认请求 GET /helloworld，你可以明天按文档改成真实路径。
     *
     * @param string $path
     * @return array<string, mixed>
     */
    public function helloWorld(string $path = '/helloworld'): array
    {
        try {
            $response = $this->httpClient->request('GET', $path);
            $body = (string) $response->getBody();

            return [
                'status_code' => $response->getStatusCode(),
                'data' => $this->decodeBody($body),
                'raw_body' => $body,
            ];
        } catch (GuzzleException $e) {
            throw new ApiException('Business.govt.nz HelloWorld request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param string $body
     * @return mixed
     */
    private function decodeBody(string $body)
    {
        $decoded = json_decode($body, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $body;
    }
}
