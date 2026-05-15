<?php

declare(strict_types=1);

namespace KimiNexus\Integrations\AbnLookup;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use KimiNexus\Core\ApiConfig;
use KimiNexus\Core\ApiException;

final class AbnLookupApiClient
{
    private const DEFAULT_CALLBACK = 'abnLookupCallback';

    /** @var ClientInterface */
    private $httpClient;

    /** @var ApiConfig */
    private $config;

    /** @var string */
    private $guid;

    public function __construct(ClientInterface $httpClient, ApiConfig $config, string $guid)
    {
        $this->httpClient = $httpClient;
        $this->config = $config;
        $this->guid = trim($guid);
    }

    /**
     * @return array<string,mixed>
     */
    public function searchByAbn(string $abn): array
    {
        $cleanAbn = preg_replace('/\D+/', '', $abn);
        if ($cleanAbn === null || strlen($cleanAbn) !== 11) {
            throw new \InvalidArgumentException('ABN must be exactly 11 digits.');
        }

        $payload = $this->requestJson('AbnDetails.aspx', [
            'abn' => $cleanAbn,
        ]);

        return [
            'query' => [
                'abn' => $cleanAbn,
            ],
            'result' => $this->normalizeAbnDetails($payload),
            'raw' => $payload,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function searchByName(string $name, int $maxResults = 20, bool $withDetails = false): array
    {
        $keyword = trim($name);
        if (strlen($keyword) < 3) {
            throw new \InvalidArgumentException('Name must be at least 3 characters for fuzzy search.');
        }

        $maxResults = max(1, min($maxResults, 100));
        $payload = $this->requestJson('MatchingNames.aspx', [
            'name' => $keyword,
            'maxResults' => $maxResults,
        ]);

        $matches = $this->extractNameMatches($payload);
        $matches = array_slice($matches, 0, $maxResults);

        if ($withDetails) {
            foreach ($matches as &$item) {
                if (!empty($item['abn']) && is_string($item['abn'])) {
                    try {
                        $detailsPayload = $this->requestJson('AbnDetails.aspx', [
                            'abn' => $item['abn'],
                        ]);
                        $item['details'] = $this->normalizeAbnDetails($detailsPayload);
                    } catch (\Throwable $e) {
                        $item['details_error'] = $e->getMessage();
                    }
                }
            }
            unset($item);
        }

        return [
            'query' => [
                'name' => $keyword,
                'max_results' => $maxResults,
                'with_details' => $withDetails,
            ],
            'count' => count($matches),
            'results' => $matches,
            'raw' => $payload,
        ];
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function requestJson(string $endpoint, array $params): array
    {
        if ($this->guid === '') {
            throw new ApiException('ABN lookup guid is empty.');
        }

        $url = rtrim($this->config->getBaseUri(), '/') . '/' . ltrim($endpoint, '/');

        try {
            $response = $this->httpClient->request('GET', $url, [
                'query' => array_merge($params, [
                    'guid' => $this->guid,
                    'callback' => self::DEFAULT_CALLBACK,
                ]),
            ]);
        } catch (GuzzleException $e) {
            throw new ApiException('ABN lookup request failed: ' . $e->getMessage(), 0, $e);
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new ApiException('ABN lookup request failed with HTTP status ' . $response->getStatusCode());
        }

        return $this->decodeJsonOrJsonp((string) $response->getBody());
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeJsonOrJsonp(string $body): array
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/^[\w$.]+\((.*)\)\s*;?$/s', $trimmed, $matches) === 1) {
            $decoded = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        throw new ApiException('Unable to parse ABN lookup response.');
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function normalizeAbnDetails(array $payload): array
    {
        $abnStatus = $this->pick($payload, ['AbnStatus', 'ABNStatus']);

        return [
            'abn' => $this->pick($payload, ['Abn', 'ABN']),
            'abn_status' => $abnStatus,
            'entity_name' => $this->pick($payload, ['EntityName', 'Name']),
            'entity_type_name' => $this->pick($payload, ['EntityTypeName']),
            'state' => $this->pick($payload, ['State']),
            'postcode' => $this->pick($payload, ['Postcode']),
            'gst' => $this->pick($payload, ['Gst', 'GST']),
            'asic_number' => $this->pick($payload, ['Acn', 'ACN']),
            'last_updated' => $this->pick($payload, ['ABNLastUpdatedDate', 'AbnLastUpdatedDate']),
            'is_active' => strtoupper((string) $abnStatus) === 'ACTIVE',
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<int,array<string,mixed>>
     */
    private function extractNameMatches(array $payload): array
    {
        $rows = [];
        $this->walkForMatches($payload, $rows);

        $unique = [];
        foreach ($rows as $row) {
            $key = (string) ($row['abn'] ?? '') . '|' . (string) ($row['name'] ?? '');
            if (!isset($unique[$key])) {
                $unique[$key] = $row;
            }
        }

        return array_values($unique);
    }

    /**
     * @param mixed $node
     * @param array<int,array<string,mixed>> $rows
     */
    private function walkForMatches($node, array &$rows): void
    {
        if (!is_array($node)) {
            return;
        }

        $abn = $this->pick($node, ['Abn', 'ABN']);
        $name = $this->pick($node, ['Name', 'EntityName', 'MainName']);

        if (!empty($abn) || !empty($name)) {
            $rows[] = [
                'abn' => $abn,
                'name' => $name,
                'score' => $this->pick($node, ['Score']),
                'state' => $this->pick($node, ['State']),
                'postcode' => $this->pick($node, ['Postcode']),
                'raw' => $node,
            ];
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $this->walkForMatches($value, $rows);
            }
        }
    }

    /**
     * @param array<string,mixed> $source
     * @param array<int,string> $keys
     * @return mixed|null
     */
    private function pick(array $source, array $keys)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source) && $source[$key] !== null && $source[$key] !== '') {
                return $source[$key];
            }
        }

        return null;
    }
}
