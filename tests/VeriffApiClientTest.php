<?php

declare(strict_types=1);

namespace KimiNexus\Tests;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use KimiNexus\Core\ApiConfig;
use KimiNexus\Core\ApiException;
use KimiNexus\Integrations\Veriff\VeriffApiClient;
use PHPUnit\Framework\TestCase;

final class VeriffApiClientTest extends TestCase
{
    private const SESSION_ID = '123e4567-e89b-12d3-a456-426614174000';
    private const USER_ID = 'c1de400b-1877-4284-8494-071d37916197';
    private const SECRET = 'test-shared-secret';

    public function testCreatesBiometricSessionWithEnrollmentIdentifier(): void
    {
        $raw = json_encode([
            'status' => 'success',
            'verification' => ['id' => self::SESSION_ID, 'url' => 'https://example.veriff.me/v/token'],
        ]);
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->once())->method('request')
            ->with(
                'POST',
                'https://api-saas.veriff.com/v1/sessions',
                $this->callback(static function (array $options): bool {
                    return $options['headers']['X-AUTH-CLIENT'] === 'test-api-key'
                        && $options['headers']['Content-Type'] === 'application/json'
                        && $options['headers']['X-HMAC-SIGNATURE'] === hash_hmac('sha256', $options['body'], self::SECRET)
                        && json_decode($options['body'], true) === [
                            'verification' => ['endUserId' => self::USER_ID, 'vendorData' => 'customer-42'],
                        ];
                })
            )
            ->willReturn($this->signedResponse($raw));

        $session = $this->client($httpClient)->createBiometricAuthenticationSession(self::USER_ID, 'customer-42');
        $this->assertSame(self::SESSION_ID, $session['id']);
        $this->assertSame('https://example.veriff.me/v/token', $session['url']);
    }

    public function testDecisionSignsSessionIdAndReturnsPendingWhenVerificationIsNull(): void
    {
        $raw = '{"status":"success","verification":null}';
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->once())->method('request')
            ->with(
                'GET',
                'https://api-saas.veriff.com/v1/sessions/' . self::SESSION_ID . '/decision',
                $this->callback(static function (array $options): bool {
                    return $options['headers']['X-HMAC-SIGNATURE'] === hash_hmac('sha256', self::SESSION_ID, self::SECRET)
                        && $options['headers']['X-AUTH-CLIENT'] === 'test-api-key';
                })
            )
            ->willReturn($this->signedResponse($raw));

        $this->assertNull($this->client($httpClient)->getDecision(self::SESSION_ID));
    }

    public function testRejectsTamperedDecision(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->method('request')->willReturn(new Response(200, [
            'X-HMAC-SIGNATURE' => hash_hmac('sha256', 'different body', self::SECRET),
        ], '{"status":"success","verification":{"status":"approved","code":9001}}'));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('signature');
        $this->client($httpClient)->getDecision(self::SESSION_ID);
    }

    public function testReturnsFullApprovedBiometricDecision(): void
    {
        $raw = json_encode([
            'status' => 'success',
            'verification' => [
                'id' => self::SESSION_ID,
                'status' => 'approved',
                'code' => 9001,
                'biometricAuthentication' => [
                    'matchedSessionId' => 'enrollment-session',
                ],
            ],
        ]);
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->method('request')->willReturn($this->signedResponse($raw));

        $decision = $this->client($httpClient)->getDecision(self::SESSION_ID);
        $this->assertSame(9001, $decision['code']);
        $this->assertSame('enrollment-session', $decision['biometricAuthentication']['matchedSessionId']);
    }

    public function testRejectsBiometricAuthenticationWithoutEnrollmentIdentifier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client($this->createMock(ClientInterface::class))->createBiometricAuthenticationSession();
    }

    private function client(ClientInterface $httpClient): VeriffApiClient
    {
        return new VeriffApiClient($httpClient, new ApiConfig('https://api-saas.veriff.com'), 'test-api-key', self::SECRET);
    }

    private function signedResponse(string $raw): Response
    {
        return new Response(200, [
            'X-HMAC-SIGNATURE' => hash_hmac('sha256', $raw, self::SECRET),
        ], $raw);
    }
}
