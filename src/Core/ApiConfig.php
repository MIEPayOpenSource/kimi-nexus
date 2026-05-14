<?php

declare(strict_types=1);

namespace KimiNexus\Core;

final class ApiConfig
{
    /** @var string */
    private $baseUri;

    /** @var string|null */
    private $apiKey;

    /** @var float */
    private $timeout;

    /** @var array */
    private $defaultHeaders;

    /**
     * @param array<string, string> $defaultHeaders
     */
    public function __construct(string $baseUri, ?string $apiKey = null, float $timeout = 10.0, array $defaultHeaders = [])
    {
        $this->baseUri = $baseUri;
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
        $this->defaultHeaders = $defaultHeaders;
    }

    public function getBaseUri(): string
    {
        return $this->baseUri;
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    /**
     * @return array<string, string>
     */
    public function buildHeaders(): array
    {
        $headers = $this->defaultHeaders;

        if ($this->apiKey !== null && $this->apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        return $headers;
    }
}
