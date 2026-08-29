<?php

namespace Rayo\SmsSdk;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Rayo\SmsSdk\Exceptions\RayoException;

class Client
{
    private GuzzleClient $httpClient;
    private string $apiKey;
    private string $baseUrl = 'https://rayo.gr'; // Replace with your exact base URL

    public function __construct(string $apiKey, array $options = [])
    {
        if (empty($apiKey)) {
            throw new RayoException("API Key cannot be empty.");
        }

        $this->apiKey = $apiKey;

        // Allow overriding base URL for testing environments if needed
        if (isset($options['base_url'])) {
            $this->baseUrl = $options['base_url'];
        }

        $this->httpClient = new GuzzleClient([
            'base_uri' => rtrim($this->baseUrl, '/') . '/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'timeout' => $options['timeout'] ?? 10.0,
        ]);
    }

    /**
     * Send an SMS via Rayo Gateway
     *
     * @param array $payload Payload following your API specs (smsBody, msisdn, senderId, etc.)
     * @return array API Response
     * @throws RayoException
     */
    public function sendSms(array $payload): array
    {
        // Enforce required fields based on your specs
        if (empty($payload['smsBody'])) {
            throw new RayoException("The 'smsBody' field is required.");
        }
        if (empty($payload['msisdn']) && empty($payload['toGroup'])) {
            throw new RayoException("Either 'msisdn' or 'toGroup' must be provided.");
        }

        return $this->request('POST', 'send_sms', $payload);
    }

    /**
     * Check current wallet balance in credits
     *
     * @return array
     * @throws RayoException
     */
    public function getBalance(): array
    {
        return $this->request('GET', 'balance_in_credits');
    }

    /**
     * Check the delivery status of a specific message
     *
     * @param string $smsRequestId The ID returned during sendSms
     * @return array
     * @throws RayoException
     */
    public function checkStatus(string $smsRequestId): array
    {
        return $this->request('GET', 'check_status', [
            'query' => ['smsRequestId' => $smsRequestId]
        ]);
    }

    /**
     * Internal request handler with error parsing
     */
    private function request(string $method, string $endpoint, array $options = []): array
    {
        try {
            // Differentiate between GET query params and POST JSON body
            $requestOptions = [];
            if ($method === 'POST') {
                $requestOptions['json'] = $options;
            } elseif ($method === 'GET' && !empty($options['query'])) {
                $requestOptions['query'] = $options['query'];
            }

            $response = $this->httpClient->request($method, $endpoint, $requestOptions);
            $body = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RayoException("Failed to parse API JSON response.");
            }

            return $body;

        } catch (GuzzleException $e) {
            // Handle HTTP connection errors (4xx, 5xx, timeouts)
            $response = $e->getResponse();
            $errorMessage = $response ? $response->getBody()->getContents() : $e->getMessage();
            throw new RayoException("Rayo API Request Failed: " . $errorMessage, $e->getCode(), $e);
        }
    }
}
