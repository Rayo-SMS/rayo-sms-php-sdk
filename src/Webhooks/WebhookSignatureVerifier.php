<?php

declare(strict_types=1);

namespace Rayo\SmsSdk\Webhooks;

use Psr\Http\Message\ServerRequestInterface;
use Rayo\SmsSdk\Exceptions\RayoException;

class WebhookSignatureVerifier
{
    private const MESSAGE_PARTS_JOIN_CHAR = ',';

    /**
     * Compute the expected HMAC-SHA256 hex digest for an incoming callback request.
     *
     * According to the API specification:
     * 1. Take the request method lower-cased ('get' or 'post').
     * 2. If POST, take the Content-Type header value; otherwise use ''.
     * 3. Take the request path (e.g. '/webhooks/sms').
     * 4. Take the payload (raw request body for POST, query string for GET).
     * 5. Take the value of the 'Date' header.
     * 6. Join these 5 parts with ',' and compute HMAC-SHA256 hex digest with signing secret.
     *
     * @throws RayoException
     */
    public static function computeSignature(
        string $signingSecret,
        string $method,
        string $requestPath,
        string $payload,
        string $dateHeader,
        string $contentType = ''
    ): string {
        if ($signingSecret === '') {
            throw new RayoException('Signing secret cannot be empty.');
        }

        $normalizedMethod = strtolower(trim($method));
        $effectiveContentType = $normalizedMethod === 'post' ? $contentType : '';
        $effectivePayload = $normalizedMethod === 'get' ? ltrim($payload, '?') : $payload;

        $stringToSign = implode(self::MESSAGE_PARTS_JOIN_CHAR, [
            $normalizedMethod,
            $effectiveContentType,
            $requestPath,
            $effectivePayload,
            $dateHeader,
        ]);

        return hash_hmac('sha256', $stringToSign, $signingSecret);
    }

    /**
     * Verify a callback signature in constant time.
     *
     * @throws RayoException
     */
    public static function verify(
        string $incomingSignature,
        string $signingSecret,
        string $method,
        string $requestPath,
        string $payload,
        string $dateHeader,
        string $contentType = ''
    ): bool {
        if (trim($incomingSignature) === '') {
            return false;
        }

        $expected = self::computeSignature(
            $signingSecret,
            $method,
            $requestPath,
            $payload,
            $dateHeader,
            $contentType
        );

        return hash_equals($expected, trim($incomingSignature));
    }

    /**
     * Verify a callback signature or throw a RayoException if invalid.
     *
     * @throws RayoException
     */
    public static function verifyOrThrow(
        string $incomingSignature,
        string $signingSecret,
        string $method,
        string $requestPath,
        string $payload,
        string $dateHeader,
        string $contentType = ''
    ): void {
        if (!self::verify(
            $incomingSignature,
            $signingSecret,
            $method,
            $requestPath,
            $payload,
            $dateHeader,
            $contentType
        )) {
            throw new RayoException('Invalid webhook callback signature.');
        }
    }

    /**
     * Verify a PSR-7 ServerRequestInterface callback request.
     *
     * @param ServerRequestInterface $request
     * @param string $signingSecret
     * @param string $signatureHeaderName Header carrying the signature (e.g. from your provider's documentation)
     * @throws RayoException
     */
    public static function verifyPsr7Request(
        ServerRequestInterface $request,
        string $signingSecret,
        string $signatureHeaderName
    ): bool {
        if (trim($signatureHeaderName) === '') {
            throw new RayoException('Signature header name cannot be empty.');
        }

        $incomingSignature = $request->getHeaderLine($signatureHeaderName);
        if ($incomingSignature === '') {
            return false;
        }

        $method = strtolower($request->getMethod());
        $contentType = $method === 'post' ? $request->getHeaderLine('Content-Type') : '';
        $requestPath = $request->getUri()->getPath();
        $payload = $method === 'post'
            ? (string) $request->getBody()
            : $request->getUri()->getQuery();
        $dateHeader = $request->getHeaderLine('Date');

        return self::verify(
            $incomingSignature,
            $signingSecret,
            $method,
            $requestPath,
            $payload,
            $dateHeader,
            $contentType
        );
    }
}
