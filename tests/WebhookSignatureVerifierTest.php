<?php

declare(strict_types=1);

namespace Rayo\SmsSdk\Tests;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Rayo\SmsSdk\Exceptions\RayoException;
use Rayo\SmsSdk\Webhooks\WebhookSignatureVerifier;

class WebhookSignatureVerifierTest extends TestCase
{
    private const TEST_SECRET = 'test-signing-secret-key';
    private const TEST_SIGNATURE_HEADER = 'X-Webhook-Signature';

    public function testPostCallbackSignatureVerification(): void
    {
        $method = 'POST';
        $contentType = 'application/json';
        $requestPath = '/webhooks/sms-delivery';
        $payload = '{"smsServerRequestId":"1396522","status":"delivered"}';
        $dateHeader = 'Wed, 21 Oct 2026 07:55:00 GMT';

        $expectedStringToSign = implode(',', [
            'post',
            $contentType,
            $requestPath,
            $payload,
            $dateHeader,
        ]);
        $expectedSignature = hash_hmac('sha256', $expectedStringToSign, self::TEST_SECRET);

        $computed = WebhookSignatureVerifier::computeSignature(
            self::TEST_SECRET,
            $method,
            $requestPath,
            $payload,
            $dateHeader,
            $contentType
        );

        $this->assertSame($expectedSignature, $computed);
        $this->assertTrue(WebhookSignatureVerifier::verify(
            $expectedSignature,
            self::TEST_SECRET,
            $method,
            $requestPath,
            $payload,
            $dateHeader,
            $contentType
        ));
    }

    public function testGetCallbackSignatureVerificationIgnoresContentType(): void
    {
        $method = 'GET';
        $requestPath = '/webhooks/sms-delivery';
        $queryString = 'clientRequestId=20240316203045&mobileNumber=306900000001&status=delivered';
        $dateHeader = 'Wed, 21 Oct 2026 07:55:00 GMT';

        // Per API spec step 2: for GET requests, content-type is treated as blank string "" even if present
        $expectedStringToSign = implode(',', [
            'get',
            '',
            $requestPath,
            $queryString,
            $dateHeader,
        ]);
        $expectedSignature = hash_hmac('sha256', $expectedStringToSign, self::TEST_SECRET);

        $this->assertTrue(WebhookSignatureVerifier::verify(
            $expectedSignature,
            self::TEST_SECRET,
            $method,
            $requestPath,
            '?' . $queryString,
            $dateHeader,
            'application/x-www-form-urlencoded'
        ));
    }

    public function testVerifyReturnsFalseForTamperedPayload(): void
    {
        $signature = WebhookSignatureVerifier::computeSignature(
            self::TEST_SECRET,
            'POST',
            '/callback',
            '{"status":"delivered"}',
            'Wed, 21 Oct 2026 07:55:00 GMT',
            'application/json'
        );

        $this->assertFalse(WebhookSignatureVerifier::verify(
            $signature,
            self::TEST_SECRET,
            'POST',
            '/callback',
            '{"status":"failure"}',
            'Wed, 21 Oct 2026 07:55:00 GMT',
            'application/json'
        ));
    }

    public function testVerifyOrThrowThrowsOnInvalidSignature(): void
    {
        $this->expectException(RayoException::class);
        $this->expectExceptionMessage('Invalid webhook callback signature.');

        WebhookSignatureVerifier::verifyOrThrow(
            'invalid-signature',
            self::TEST_SECRET,
            'POST',
            '/callback',
            '{}',
            'Wed, 21 Oct 2026 07:55:00 GMT',
            'application/json'
        );
    }

    public function testVerifyPsr7PostRequest(): void
    {
        $body = '{"smsServerRequestId":"1396522","status":"delivered"}';
        $date = 'Wed, 21 Oct 2026 07:55:00 GMT';
        $signature = WebhookSignatureVerifier::computeSignature(
            self::TEST_SECRET,
            'POST',
            '/webhooks/callback',
            $body,
            $date,
            'application/json'
        );

        $request = new ServerRequest(
            'POST',
            'https://client.example.invalid/webhooks/callback',
            [
                'Content-Type'              => 'application/json',
                'Date'                      => $date,
                self::TEST_SIGNATURE_HEADER => $signature,
            ],
            $body
        );

        $this->assertTrue(WebhookSignatureVerifier::verifyPsr7Request(
            $request,
            self::TEST_SECRET,
            self::TEST_SIGNATURE_HEADER
        ));
    }
}
