<?php

declare(strict_types=1);

namespace Rayo\SmsSdk;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Rayo\SmsSdk\Exceptions\RayoException;
use Rayo\SmsSdk\Models\BalanceResponse;
use Rayo\SmsSdk\Models\DeleteScheduleResponse;
use Rayo\SmsSdk\Models\SmsDeliveryStatusItem;
use Rayo\SmsSdk\Models\SmsRequest;
use Rayo\SmsSdk\Models\SmsResponse;

class Client
{
    public const DEFAULT_TIMEOUT = 10.0;
    public const API_VERSION_PREFIX = 'v1';

    private ClientInterface $httpClient;
    private string $apiKey;
    private string $baseUrl;

    /**
     * @param string $apiKey The private API key from Actions -> Settings.
     * @param string|array<string, mixed> $baseUrlOrOptions The API base URL (e.g. "https://<base-url>") or an options array containing 'base_url'.
     * @param array<string, mixed> $options Additional Guzzle/SDK options ('timeout', 'http_client', 'handler', 'headers', etc.).
     * @throws RayoException
     */
    public function __construct(string $apiKey, $baseUrlOrOptions = [], array $options = [])
    {
        $trimmedApiKey = trim($apiKey);
        if ($trimmedApiKey === '') {
            throw new RayoException('API Key cannot be empty.');
        }

        // Strip a leading "Bearer " prefix if the caller passed one so we don't duplicate it
        $this->apiKey = (string) preg_replace('/^Bearer\s+/i', '', $trimmedApiKey);
        if ($this->apiKey === '') {
            throw new RayoException('API Key cannot be empty.');
        }

        if (is_string($baseUrlOrOptions)) {
            $options['base_url'] = $baseUrlOrOptions;
        } elseif (is_array($baseUrlOrOptions)) {
            $options = array_merge($baseUrlOrOptions, $options);
        } else {
            throw new RayoException('The second constructor argument must be a base URL string or an options array.');
        }

        $rawBaseUrl = isset($options['base_url']) && is_string($options['base_url'])
            ? trim($options['base_url'])
            : '';

        if ($rawBaseUrl === '') {
            throw new RayoException("Base URL is required (e.g. 'https://<base-url>').");
        }

        $this->baseUrl = self::normalizeBaseUrl($rawBaseUrl);

        if (isset($options['http_client']) && $options['http_client'] instanceof ClientInterface) {
            $this->httpClient = $options['http_client'];
            return;
        }

        $guzzleConfig = [
            'base_uri' => $this->baseUrl,
            'headers'  => array_merge([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ], isset($options['headers']) && is_array($options['headers']) ? $options['headers'] : []),
            'timeout'  => isset($options['timeout']) ? (float) $options['timeout'] : self::DEFAULT_TIMEOUT,
        ];

        if (isset($options['handler'])) {
            $guzzleConfig['handler'] = $options['handler'];
        }

        if (isset($options['connect_timeout'])) {
            $guzzleConfig['connect_timeout'] = (float) $options['connect_timeout'];
        }

        if (isset($options['verify'])) {
            $guzzleConfig['verify'] = $options['verify'];
        }

        $this->httpClient = new GuzzleClient($guzzleConfig);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Send an SMS or schedule an SMS for future delivery (`POST /v1/send_sms`).
     *
     * @param SmsRequest|array<string, mixed> $payload SmsRequest model or associative array with keys:
     *                                                 fromLabel, smsBody, toMobileNumber, toGroup,
     *                                                 clientRequestId, callbackWhenProcessedFlag,
     *                                                 simulateSendFlag, concatFlag, scheduledAt
     * @return SmsResponse
     * @throws RayoException
     */
    public function sendSms($payload): SmsResponse
    {
        if ($payload instanceof SmsRequest) {
            $smsRequest = $payload;
        } elseif (is_array($payload)) {
            $smsRequest = SmsRequest::fromArray($payload);
        } else {
            throw new RayoException('Payload must be an instance of SmsRequest or an associative array.');
        }

        $data = $this->request('POST', 'send_sms', [
            'json' => $smsRequest->toArray(),
        ]);

        return SmsResponse::fromArray($data);
    }

    /**
     * Retrieve current account balance in credits (`GET /v1/balance_in_credits`).
     *
     * @return BalanceResponse
     * @throws RayoException
     */
    public function getBalance(): BalanceResponse
    {
        $data = $this->request('GET', 'balance_in_credits');

        return BalanceResponse::fromArray($data);
    }

    /**
     * Alias for getBalance() matching the OpenAPI operationId (`GET /v1/balance_in_credits`).
     *
     * @return BalanceResponse
     * @throws RayoException
     */
    public function getBalanceInCredits(): BalanceResponse
    {
        return $this->getBalance();
    }

    /**
     * Check the delivery status of an SMS request (`GET /v1/check_status`).
     *
     * Supply either `$smsServerRequestId` or `$clientRequestId`, but not both.
     *
     * @param string|null $smsServerRequestId Server-generated request UUID returned when sending an SMS.
     * @param string|null $clientRequestId    Client-assigned request ID provided when sending an SMS.
     * @return SmsDeliveryStatusItem[]
     * @throws RayoException
     */
    public function checkStatus(?string $smsServerRequestId = null, ?string $clientRequestId = null): array
    {
        $hasServerId = $smsServerRequestId !== null && trim($smsServerRequestId) !== '';
        $hasClientId = $clientRequestId !== null && trim($clientRequestId) !== '';

        if (!$hasServerId && !$hasClientId) {
            throw new RayoException("Either 'smsServerRequestId' or 'clientRequestId' must be provided.");
        }

        if ($hasServerId && $hasClientId) {
            throw new RayoException("Provide either 'smsServerRequestId' or 'clientRequestId', not both.");
        }

        $query = $hasServerId
            ? ['smsServerRequestId' => trim((string) $smsServerRequestId)]
            : ['clientRequestId' => trim((string) $clientRequestId)];

        $data = $this->request('GET', 'check_status', [
            'query' => $query,
        ]);

        $items = [];
        foreach ($data as $row) {
            if (is_array($row)) {
                $items[] = SmsDeliveryStatusItem::fromArray($row);
            }
        }

        return $items;
    }

    /**
     * Check delivery status by server-generated request ID (`GET /v1/check_status?smsServerRequestId=...`).
     *
     * @return SmsDeliveryStatusItem[]
     * @throws RayoException
     */
    public function checkStatusByServerRequestId(string $smsServerRequestId): array
    {
        return $this->checkStatus($smsServerRequestId, null);
    }

    /**
     * Check delivery status by client-provided request ID (`GET /v1/check_status?clientRequestId=...`).
     *
     * @return SmsDeliveryStatusItem[]
     * @throws RayoException
     */
    public function checkStatusByClientRequestId(string $clientRequestId): array
    {
        return $this->checkStatus(null, $clientRequestId);
    }

    /**
     * Delete a scheduled SMS request before it fires (`DELETE /v1/sms_request_schedules/{id}`).
     *
     * @param string $scheduleId Identifier returned as `smsRequestScheduleId` when the SMS was scheduled.
     * @return DeleteScheduleResponse
     * @throws RayoException
     */
    public function deleteSchedule(string $scheduleId): DeleteScheduleResponse
    {
        $trimmedId = trim($scheduleId);
        if ($trimmedId === '') {
            throw new RayoException('Schedule ID cannot be empty.');
        }

        $data = $this->request('DELETE', 'sms_request_schedules/' . rawurlencode($trimmedId));

        return DeleteScheduleResponse::fromArray($data);
    }

    /**
     * Alias for deleteSchedule() matching the OpenAPI operationId.
     *
     * @throws RayoException
     */
    public function deleteSmsRequestSchedule(string $scheduleId): DeleteScheduleResponse
    {
        return $this->deleteSchedule($scheduleId);
    }

    /**
     * Normalize base URL so it always ends with `/v1/`.
     *
     * @throws RayoException
     */
    private static function normalizeBaseUrl(string $baseUrl): string
    {
        $parsed = parse_url($baseUrl);
        if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
            throw new RayoException("Invalid base URL provided. Expected a full URL such as 'https://<base-url>'.");
        }

        $scheme = strtolower($parsed['scheme']);
        if (!in_array($scheme, ['https', 'http'], true)) {
            throw new RayoException("Invalid base URL scheme. Only 'https' and 'http' are supported.");
        }

        $trimmed = rtrim($baseUrl, '/');
        if (!preg_match('#/' . preg_quote(self::API_VERSION_PREFIX, '#') . '$#i', $trimmed)) {
            $trimmed .= '/' . self::API_VERSION_PREFIX;
        }

        return $trimmed . '/';
    }

    /**
     * Internal HTTP request handler with structured error parsing.
     *
     * @param array<string, mixed> $options
     * @return array<mixed>
     * @throws RayoException
     */
    private function request(string $method, string $endpoint, array $options = []): array
    {
        $requestOptions = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept'        => 'application/json',
            ],
        ];

        if (isset($options['json'])) {
            $requestOptions['headers']['Content-Type'] = 'application/json';
            $requestOptions['json'] = $options['json'];
        }

        if (!empty($options['query']) && is_array($options['query'])) {
            $requestOptions['query'] = $options['query'];
        }

        try {
            $response = $this->httpClient->request($method, ltrim($endpoint, '/'), $requestOptions);
            $rawBody = (string) $response->getBody();

            if ($rawBody === '') {
                return [];
            }

            $decoded = json_decode($rawBody, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                throw new RayoException(
                    'Failed to parse API JSON response.',
                    $response->getStatusCode(),
                    null,
                    $response->getStatusCode(),
                    null,
                    $rawBody
                );
            }

            // SmsRequestSchedulesController#destroy_error renders HTTP 200 with {"error": "..."}
            if (isset($decoded['error']) && is_string($decoded['error']) && $decoded['error'] !== '' && !isset($decoded['result'])) {
                throw new RayoException(
                    'API Request Failed: ' . $decoded['error'],
                    $response->getStatusCode(),
                    null,
                    $response->getStatusCode(),
                    $decoded,
                    $rawBody
                );
            }

            return $decoded;
        } catch (GuzzleException $e) {
            $response = $e instanceof RequestException ? $e->getResponse() : null;
            $statusCode = $response !== null ? $response->getStatusCode() : ($e->getCode() > 0 ? (int) $e->getCode() : null);
            $rawBody = $response !== null ? (string) $response->getBody() : null;
            $decodedError = null;

            if ($rawBody !== null && $rawBody !== '') {
                $parsed = json_decode($rawBody, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                    $decodedError = $parsed;
                }
            }

            $errorDetail = self::extractErrorMessage($decodedError, $rawBody, $statusCode, $e->getMessage());

            throw new RayoException(
                'API Request Failed: ' . $errorDetail,
                $statusCode ?? 0,
                $e,
                $statusCode,
                $decodedError,
                $rawBody
            );
        }
    }

    /**
     * @param array<string, mixed>|null $decodedError
     */
    private static function extractErrorMessage(
        ?array $decodedError,
        ?string $rawBody,
        ?int $statusCode,
        string $fallbackMessage
    ): string {
        if (is_array($decodedError)) {
            $parts = [];
            if (isset($decodedError['error']) && is_string($decodedError['error']) && $decodedError['error'] !== '') {
                $parts[] = $decodedError['error'];
            }
            if (
                isset($decodedError['result']['resultDescription'])
                && is_string($decodedError['result']['resultDescription'])
                && $decodedError['result']['resultDescription'] !== ''
            ) {
                $parts[] = $decodedError['result']['resultDescription'];
            }

            if ($parts !== []) {
                return implode(' - ', $parts);
            }
        }

        if ($rawBody !== null && trim($rawBody) !== '') {
            return trim($rawBody);
        }

        if ($statusCode === 401) {
            return 'Unauthorized (401): Invalid or inactive API key.';
        }

        if ($statusCode === 404) {
            return 'Not Found (404): The requested resource was not found.';
        }

        return $fallbackMessage;
    }
}

