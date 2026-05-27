<?php

declare(strict_types=1);

namespace KimiNexus\Integrations\BusinessGovtNz;

use GuzzleHttp\Client;
use KimiNexus\Core\ApiConfig;

final class BusinessGovtNzGateway
{
    public static function make(ApiConfig $config): BusinessGovtNzApiClient
    {
        $headers = $config->buildHeaders();

        if (!isset($headers['Ocp-Apim-Subscription-Key']) && isset($headers['Authorization'])) {
            $prefix = 'Bearer ';
            if (strpos($headers['Authorization'], $prefix) === 0) {
                $token = trim(substr($headers['Authorization'], strlen($prefix)));
                if ($token !== '') {
                    $headers['Ocp-Apim-Subscription-Key'] = $token;
                }
            }
        }

        $httpClient = new Client([
            'base_uri' => $config->getBaseUri(),
            'timeout' => $config->getTimeout(),
            'headers' => $headers,
        ]);

        return new BusinessGovtNzApiClient($httpClient, $config);
    }
}
