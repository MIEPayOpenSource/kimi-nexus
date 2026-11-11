<?php

declare(strict_types=1);

namespace KimiNexus\Integrations\Veriff;

use GuzzleHttp\Client;
use KimiNexus\Core\ApiConfig;

final class VeriffGateway
{
    public static function make(ApiConfig $config, string $apiKey, string $sharedSecret): VeriffApiClient
    {
        $httpClient = new Client([
            'base_uri' => $config->getBaseUri(),
            'timeout' => $config->getTimeout(),
            'allow_redirects' => false,
        ]);

        return new VeriffApiClient($httpClient, $config, $apiKey, $sharedSecret);
    }
}
