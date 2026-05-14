<?php

declare(strict_types=1);

namespace KimiNexus\Integrations\Example;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use KimiNexus\Core\ApiConfig;
use KimiNexus\Core\ApiException;

final class ExampleApiClient
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
     * @return array{status_code:int,body:string}
     */
    public function ping(): array
    {
        try {
            $response = $this->httpClient->request('GET', '/ping');
            $body = (string) $response->getBody();

            return [
                'status_code' => $response->getStatusCode(),
                'body' => $body,
            ];
        } catch (GuzzleException $e) {
            throw new ApiException('Example API request failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
