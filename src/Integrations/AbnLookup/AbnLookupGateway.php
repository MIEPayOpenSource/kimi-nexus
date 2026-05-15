<?php

declare(strict_types=1);

namespace KimiNexus\Integrations\AbnLookup;

use KimiNexus\Core\ApiConfig;
use KimiNexus\Core\HttpClientFactory;

final class AbnLookupGateway
{
    public static function make(ApiConfig $config, string $guid): AbnLookupApiClient
    {
        $httpClient = (new HttpClientFactory())->create($config);

        return new AbnLookupApiClient($httpClient, $config, $guid);
    }
}
