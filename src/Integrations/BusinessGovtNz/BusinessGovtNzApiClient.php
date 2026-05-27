<?php

declare(strict_types=1);

namespace KimiNexus\Integrations\BusinessGovtNz;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use KimiNexus\Core\ApiConfig;
use KimiNexus\Core\ApiException;

final class BusinessGovtNzApiClient
{
    private const ENTITIES_RESOURCE_PATH = '/nzbn/v5/entities';

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

    /**
     * Search the NZBN directory by entity name.
     *
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function searchEntitiesByName(string $searchTerm, array $filters = []): array
    {
        $keyword = trim($searchTerm);
        if ($keyword === '') {
            throw new \InvalidArgumentException('searchTerm is required.');
        }

        $query = [
            'search-term' => $keyword,
        ];

        $entityStatus = $this->normalizeCsvFilter($filters, 'entity_status');
        if ($entityStatus !== null) {
            $query['entity-status'] = $entityStatus;
        }

        $entityType = $this->normalizeCsvFilter($filters, 'entity_type');
        if ($entityType !== null) {
            $query['entity-type'] = $entityType;
        }

        $industryCode = $this->normalizeScalarFilter($filters, 'industry_code');
        if ($industryCode !== null) {
            $query['industry-code'] = $industryCode;
        }

        $page = $this->normalizeIntFilter($filters, 'page');
        if ($page !== null) {
            $query['page'] = $page;
        }

        $pageSize = $this->normalizeIntFilter($filters, 'page_size');
        if ($pageSize !== null) {
            $query['page-size'] = $pageSize;
        }

        $path = $this->buildNzbnPath(self::ENTITIES_RESOURCE_PATH);

        try {
            $response = $this->httpClient->request('GET', $path, [
                'query' => $query,
            ]);
        } catch (GuzzleException $e) {
            throw new ApiException('Business.govt.nz entity search request failed: ' . $e->getMessage(), 0, $e);
        }

        $statusCode = $response->getStatusCode();
        $rawBody = (string) $response->getBody();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new ApiException('Business.govt.nz entity search request failed with HTTP status ' . $statusCode);
        }

        $decoded = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException('Unable to parse Business.govt.nz entity search response.');
        }

        return [
            'status_code' => $statusCode,
            'query' => [
                'search_term' => $keyword,
                'entity_status' => $entityStatus,
                'entity_type' => $entityType,
                'industry_code' => $industryCode,
                'page' => $page,
                'page_size' => $pageSize,
            ],
            'data' => $decoded,
            'raw_body' => $rawBody,
        ];
    }

    /**
     * Get details for an NZBN entity.
     *
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function viewEntityByNzbn(string $nzbn, array $options = []): array
    {
        $entityNzbn = trim($nzbn);
        if ($entityNzbn === '') {
            throw new \InvalidArgumentException('nzbn is required.');
        }

        $path = $this->buildNzbnPath(self::ENTITIES_RESOURCE_PATH . '/' . rawurlencode($entityNzbn));
        $headers = $this->buildViewEntityHeaders($options);
        $requestOptions = [];
        if (count($headers) > 0) {
            $requestOptions['headers'] = $headers;
        }

        try {
            $response = $this->httpClient->request('GET', $path, $requestOptions);
        } catch (GuzzleException $e) {
            throw new ApiException('Business.govt.nz view entity request failed: ' . $e->getMessage(), 0, $e);
        }

        $statusCode = $response->getStatusCode();
        $rawBody = (string) $response->getBody();
        if ($statusCode === 304) {
            return [
                'status_code' => $statusCode,
                'query' => [
                    'nzbn' => $entityNzbn,
                ],
                'request_headers' => [
                    'api-business-govt-nz-Request-Id' => isset($headers['api-business-govt-nz-Request-Id']) ? $headers['api-business-govt-nz-Request-Id'] : null,
                    'If-None-Match' => isset($headers['If-None-Match']) ? $headers['If-None-Match'] : null,
                ],
                'response_headers' => [
                    'etag' => $response->getHeaderLine('ETag') !== '' ? $response->getHeaderLine('ETag') : null,
                ],
                'not_modified' => true,
                'data' => null,
                'raw_body' => $rawBody,
            ];
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new ApiException('Business.govt.nz view entity request failed with HTTP status ' . $statusCode);
        }

        $decoded = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException('Unable to parse Business.govt.nz view entity response.');
        }

        return [
            'status_code' => $statusCode,
            'query' => [
                'nzbn' => $entityNzbn,
            ],
            'request_headers' => [
                'api-business-govt-nz-Request-Id' => isset($headers['api-business-govt-nz-Request-Id']) ? $headers['api-business-govt-nz-Request-Id'] : null,
                'If-None-Match' => isset($headers['If-None-Match']) ? $headers['If-None-Match'] : null,
            ],
            'response_headers' => [
                'etag' => $response->getHeaderLine('ETag') !== '' ? $response->getHeaderLine('ETag') : null,
            ],
            'not_modified' => false,
            'data' => $decoded,
            'raw_body' => $rawBody,
        ];
    }

    /**
     * @param array<string,mixed> $filters
     */
    private function normalizeScalarFilter(array $filters, string $key): ?string
    {
        if (!array_key_exists($key, $filters)) {
            return null;
        }

        $value = trim((string) $filters[$key]);
        if ($value === '') {
            return null;
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $filters
     */
    private function normalizeCsvFilter(array $filters, string $key): ?string
    {
        if (!array_key_exists($key, $filters)) {
            return null;
        }

        $value = $filters[$key];
        if (is_array($value)) {
            $items = [];
            foreach ($value as $item) {
                $text = trim((string) $item);
                if ($text !== '') {
                    $items[] = $text;
                }
            }

            if (count($items) === 0) {
                return null;
            }

            return implode(',', $items);
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return $text;
    }

    /**
     * @param array<string,mixed> $filters
     */
    private function normalizeIntFilter(array $filters, string $key): ?int
    {
        if (!array_key_exists($key, $filters)) {
            return null;
        }

        $raw = $filters[$key];
        if (is_bool($raw) || $raw === null) {
            return null;
        }

        if (is_int($raw)) {
            if ($raw < 0) {
                throw new \InvalidArgumentException($key . ' must be greater than or equal to 0.');
            }

            return $raw;
        }

        $text = trim((string) $raw);
        if ($text === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $text) === 1) {
            return (int) $text;
        }

        if (!is_numeric($text)) {
            throw new \InvalidArgumentException($key . ' must be an integer.');
        }

        throw new \InvalidArgumentException($key . ' must be a non-negative integer.');
    }

    /**
     * @param array<string,mixed> $options
     * @return array<string,string>
     */
    private function buildViewEntityHeaders(array $options): array
    {
        $headers = [];

        $requestId = $this->normalizeScalarFilter($options, 'request_id');
        if ($requestId !== null) {
            $headers['api-business-govt-nz-Request-Id'] = $requestId;
        }

        $ifNoneMatch = $this->normalizeScalarFilter($options, 'if_none_match');
        if ($ifNoneMatch !== null) {
            $headers['If-None-Match'] = $ifNoneMatch;
        }

        return $headers;
    }

    private function buildNzbnPath(string $resourcePath): string
    {
        $baseUri = strtolower(rtrim($this->config->getBaseUri(), '/'));
        $prefix = '/gateway';
        if (substr($baseUri, -8) === '/sandbox') {
            $prefix = '/sandbox';
        }

        return $prefix . $resourcePath;
    }
}
