<?php

declare(strict_types=1);

namespace KimiNexus\Integrations\BusinessGovtNz;

use KimiNexus\Core\ApiConfig;
use KimiNexus\Core\HttpClientFactory;

final class BusinessGovtNzGateway
{
    public static function make(ApiConfig $config): BusinessGovtNzApiClient
    {
        $httpClient = (new HttpClientFactory())->create($config);

        return new BusinessGovtNzApiClient($httpClient, $config);
    }
}
