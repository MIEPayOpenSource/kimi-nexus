<?php

declare(strict_types=1);

namespace KimiNexus\Integrations\Veriff;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use KimiNexus\Core\ApiConfig;
use KimiNexus\Core\ApiException;

final class VeriffApiClient
{
    /** @var ClientInterface */
    private $httpClient;

    /** @var ApiConfig */
    private $config;

    /** @var string */
    private $apiKey;

    /** @var string */
    private $sharedSecret;

    public function __construct(ClientInterface $httpClient, ApiConfig $config, string $apiKey, string $sharedSecret)
    {
        if (trim($apiKey) === '' || trim($sharedSecret) === '') {
            throw new \InvalidArgumentException('Veriff API key and shared secret are required.');
        }

        $this->httpClient = $httpClient;
        $this->config = $config;
        $this->apiKey = $apiKey;
        $this->sharedSecret = $sharedSecret;
    }

    /**
     * Create a hosted Veriff session. The returned URL is for the end user's browser.
     *
     * @param array<string,mixed> $verification
     * @return array<string,mixed>
     */
    public function createSession(array $verification = []): array
    {
        $body = json_encode(['verification' => (object) $verification]);
        if ($body === false) {
            throw new \InvalidArgumentException('Unable to encode Veriff session data.');
        }

        $result = $this->request('POST', '/v1/sessions', $body, $this->sign($body), true);
        $session = $result['verification'] ?? null;
        if (!is_array($session) || !isset($session['id'], $session['url']) ||
            !is_string($session['id']) || trim($session['id']) === '' ||
            !is_string($session['url']) || parse_url($session['url'], PHP_URL_SCHEME) !== 'https' ||
            !filter_var($session['url'], FILTER_VALIDATE_URL)) {
            throw new ApiException('Veriff did not return a valid session ID and HTTPS URL.');
        }

        return $session;
    }

    /**
     * Use the same identifier as in the approved enrollment session.
     *
     * @return array<string,mixed>
     */
    public function createBiometricAuthenticationSession(?string $endUserId = null, ?string $vendorData = null): array
    {
        $verification = [];
        if ($endUserId !== null && trim($endUserId) !== '') {
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', trim($endUserId)) !== 1) {
                throw new \InvalidArgumentException('Veriff endUserId must be a UUID.');
            }
            $verification['endUserId'] = trim($endUserId);
        }
        if ($vendorData !== null && trim($vendorData) !== '') {
            if (strlen(trim($vendorData)) > 1000) {
                throw new \InvalidArgumentException('Veriff vendorData must be at most 1000 bytes.');
            }
            $verification['vendorData'] = trim($vendorData);
        }
        if ($verification === []) {
            throw new \InvalidArgumentException('An enrollment endUserId or vendorData is required for biometric authentication.');
        }

        return $this->createSession($verification);
    }

    /**
     * A null verification means the decision is not ready yet.
     *
     * @return array<string,mixed>|null
     */
    public function getDecision(string $sessionId): ?array
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            throw new \InvalidArgumentException('Veriff session ID is required.');
        }

        $result = $this->request(
            'GET',
            '/v1/sessions/' . rawurlencode($sessionId) . '/decision',
            null,
            $this->sign($sessionId),
            true
        );

        if (!array_key_exists('verification', $result)) {
            throw new ApiException('Veriff decision response is missing the verification field.');
        }
        $decision = $result['verification'];
        if ($decision === null) {
            return null;
        }
        if (!is_array($decision)) {
            throw new ApiException('Veriff decision has an invalid verification field.');
        }
        if (isset($decision['id']) && $decision['id'] !== $sessionId) {
            throw new ApiException('Veriff decision session ID does not match the request.');
        }

        return $decision;
    }

    /**
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, ?string $body = null, ?string $signature = null, bool $verifyResponseSignature = false): array
    {
        $headers = [
            'X-AUTH-CLIENT' => $this->apiKey,
            'Content-Type' => 'application/json',
        ];
        if ($signature !== null) {
            $headers['X-HMAC-SIGNATURE'] = $signature;
        }

        $options = ['headers' => $headers, 'http_errors' => false, 'allow_redirects' => false];
        if ($body !== null) {
            $options['body'] = $body;
        }

        try {
            $response = $this->httpClient->request($method, rtrim($this->config->getBaseUri(), '/') . $path, $options);
        } catch (GuzzleException $e) {
            throw new ApiException('Veriff request failed: ' . $e->getMessage(), 0, $e);
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new ApiException('Veriff request failed with HTTP status ' . $statusCode . '.', $statusCode);
        }

        $rawBody = (string) $response->getBody();
        if ($verifyResponseSignature) {
            $actual = $response->getHeaderLine('X-HMAC-SIGNATURE');
            if (preg_match('/^[a-f0-9]{64}$/i', $actual) !== 1 || !hash_equals($this->sign($rawBody), strtolower($actual))) {
                throw new ApiException('Veriff response signature did not match.');
            }
        }

        $decoded = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new ApiException('Unable to parse Veriff response.');
        }
        if (($decoded['status'] ?? null) !== 'success') {
            throw new ApiException('Veriff returned an unsuccessful response.');
        }

        return $decoded;
    }

    private function sign(string $data): string
    {
        return hash_hmac('sha256', $data, $this->sharedSecret);
    }
}
