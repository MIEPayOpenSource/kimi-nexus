<?php

declare(strict_types=1);

namespace KimiNexus\Core;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

final class HttpClientFactory
{
    public function create(ApiConfig $config): ClientInterface
    {
        return new Client([
            'base_uri' => $config->getBaseUri(),
            'timeout' => $config->getTimeout(),
            'headers' => $config->buildHeaders(),
        ]);
    }
}
