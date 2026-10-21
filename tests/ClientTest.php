<?php

declare(strict_types=1);

namespace Rayo\SmsSdk\Tests;

use DateTimeImmutable;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Rayo\SmsSdk\Client;
use Rayo\SmsSdk\Exceptions\RayoException;
use Rayo\SmsSdk\Models\BalanceResponse;
use Rayo\SmsSdk\Models\DeleteScheduleResponse;
use Rayo\SmsSdk\Models\DeliveryStatus;
use Rayo\SmsSdk\Models\SmsDeliveryStatusItem;
use Rayo\SmsSdk\Models\SmsRequest;
use Rayo\SmsSdk\Models\SmsResponse;

class ClientTest extends TestCase
{
    private const TEST_API_KEY = '00000000-0000-0000-0000-000000000000';
    private const TEST_BASE_URL = 'https://api.example.invalid';

    /**
     * @param Response[] $responses
     * @param array<int, array<string, mixed>> $history
     */
    private function createMockClient(array $responses, array &$history = [], string $baseUrl = self::TEST_BASE_URL): Client
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($history));

        return new Client(self::TEST_API_KEY, $baseUrl, [
            'handler' => $handlerStack,
        ]);
    }

    public function testConstructorRequiresNonEmptyApiKey(): void
    {
        $this->expectException(RayoException::class);
        $this->expectExceptionMessage('API Key cannot be empty.');

        new Client('   ', self::TEST_BASE_URL);
    }

    public function testConstructorRequiresBaseUrl(): void
    {
        $this->expectException(RayoException::class);
        $this->expectExceptionMessage('Base URL is required');

        new Client(self::TEST_API_KEY);
    }

    public function testConstructorAcceptsOptionsArrayAsSecondArgument(): void
    {
        $client = new Client(self::TEST_API_KEY, [
            'base_url' => 'https://api.example.invalid',
            'timeout'  => 5.0,
        ]);

        $this->assertSame('https://api.example.invalid/v1/', $client->getBaseUrl());
    }

    public function testBaseUrlNormalization(): void
    {
        $client1 = new Client(self::TEST_API_KEY, 'https://api.example.invalid');
        $this->assertSame('https://api.example.invalid/v1/', $client1->getBaseUrl());

        $client2 = new Client(self::TEST_API_KEY, 'https://api.example.invalid/');
        $this->assertSame('https://api.example.invalid/v1/', $client2->getBaseUrl());

        $client3 = new Client(self::TEST_API_KEY, 'https://api.example.invalid/v1');
        $this->assertSame('https://api.example.invalid/v1/', $client3->getBaseUrl());

        $client4 = new Client(self::TEST_API_KEY, 'https://api.example.invalid/v1/');
        $this->assertSame('https://api.example.invalid/v1/', $client4->getBaseUrl());
    }

    public function testConstructorRejectsInvalidBaseUrl(): void
    {
        $this->expectException(RayoException::class);
        $this->expectExceptionMessage('Invalid base URL provided');

        new Client(self::TEST_API_KEY, 'not-a-valid-url');
    }

    public function testBearerPrefixInApiKeyIsNotDuplicated(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'request_id' => 'req-1',
                'balance'    => ['credits' => 100.0],
            ])),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($history));

        $client = new Client('Bearer ' . self::TEST_API_KEY, self::TEST_BASE_URL, [
            'handler' => $handlerStack,
        ]);

        $client->getBalance();

        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('Bearer ' . self::TEST_API_KEY, $request->getHeaderLine('Authorization'));
    }

    public function testSendSmsWithArrayPayload(): void
    {
        $history = [];
        $responsePayload = [
            'result' => [
                'username'             => 'demo_user',
                'resultCode'           => '1',
                'resultDescription'    => 'SUCCESS',
                'clientRequestId'      => 'req-9543212',
                'smsServerRequestId'   => 'cc479845-0f15-4ae7-b645-6ef6489868c1',
                'smsRequestScheduleId' => null,
            ],
            'error' => null,
        ];

        $client = $this->createMockClient([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($responsePayload)),
        ], $history);

        $response = $client->sendSms([
            'fromLabel'                 => 'SENDERID',
            'toMobileNumber'            => '306900000001',
            'smsBody'                   => 'Hello from PHP SDK!',
            'clientRequestId'           => 'req-9543212',
            'callbackWhenProcessedFlag' => true,
            'simulateSendFlag'          => true,
            'concatFlag'                => true,
        ]);

        $this->assertInstanceOf(SmsResponse::class, $response);
        $this->assertTrue($response->isSuccess());
        $this->assertFalse($response->isScheduled());
        $this->assertSame('demo_user', $response->getUsername());
        $this->assertSame('1', $response->getResultCode());
        $this->assertSame('SUCCESS', $response->getResultDescription());
        $this->assertSame('req-9543212', $response->getClientRequestId());
        $this->assertSame('cc479845-0f15-4ae7-b645-6ef6489868c1', $response->getSmsServerRequestId());
        $this->assertNull($response->getSmsRequestScheduleId());
        $this->assertNull($response->getError());

        // ArrayAccess compatibility
        $this->assertSame('cc479845-0f15-4ae7-b645-6ef6489868c1', $response['result']['smsServerRequestId']);

        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api.example.invalid/v1/send_sms', (string) $request->getUri());
        $this->assertSame('Bearer ' . self::TEST_API_KEY, $request->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));

        $sentBody = json_decode((string) $request->getBody(), true);
        $this->assertSame([
            'fromLabel'                 => 'SENDERID',
            'smsBody'                   => 'Hello from PHP SDK!',
            'toMobileNumber'            => '306900000001',
            'clientRequestId'           => 'req-9543212',
            'callbackWhenProcessedFlag' => true,
            'simulateSendFlag'          => true,
            'concatFlag'                => true,
        ], $sentBody);
    }

    public function testSendSmsWithModelAndScheduledAt(): void
    {
        $history = [];
        $scheduledTime = new DateTimeImmutable('2026-12-13T11:34:29+02:00');
        $responsePayload = [
            'result' => [
                'username'             => 'demo_user',
                'resultCode'           => '1',
                'resultDescription'    => 'SUCCESS',
                'clientRequestId'      => 'sched-1',
                'smsServerRequestId'   => null,
                'smsRequestScheduleId' => 'e0b107da-669a-40f5-b4d0-24fc70409307',
            ],
            'error' => null,
        ];

        $client = $this->createMockClient([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($responsePayload)),
        ], $history);

        $smsRequest = new SmsRequest(
            'SENDERID',
            'Scheduled promotional message',
            ['306900000001', '306900000002'],
            ['VIP Customers', 'Newsletter'],
            'sched-1',
            false,
            true,
            true,
            $scheduledTime
        );

        $response = $client->sendSms($smsRequest);

        $this->assertTrue($response->isSuccess());
        $this->assertTrue($response->isScheduled());
        $this->assertNull($response->getSmsServerRequestId());
        $this->assertSame('e0b107da-669a-40f5-b4d0-24fc70409307', $response->getSmsRequestScheduleId());

        $sentBody = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('306900000001;306900000002', $sentBody['toMobileNumber']);
        $this->assertSame('VIP Customers;Newsletter', $sentBody['toGroup']);
        $this->assertSame('2026-12-13T11:34:29+02:00', $sentBody['scheduledAt']);
    }

    public function testGetBalance(): void
    {
        $history = [];
        $responsePayload = [
            'request_id' => '7d6939ec-7213-4bf9-9b41-2902b54cfa71',
            'balance'    => [
                'credits' => 4240.5,
            ],
        ];

        $client = $this->createMockClient([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($responsePayload)),
        ], $history);

        $balance = $client->getBalance();

        $this->assertInstanceOf(BalanceResponse::class, $balance);
        $this->assertSame('7d6939ec-7213-4bf9-9b41-2902b54cfa71', $balance->getRequestId());
        $this->assertSame(4240.5, $balance->getCredits());
        $this->assertSame(4240.5, $balance['balance']['credits']);

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('https://api.example.invalid/v1/balance_in_credits', (string) $request->getUri());
    }

    public function testCheckStatusByServerRequestId(): void
    {
        $history = [];
        $responsePayload = [
            [
                'smsServerRequestId'       => 'cc479845-0f15-4ae7-b645-6ef6489868c1',
                'clientRequestId'          => '20240316203045',
                'internalSmsOutDeliveryId' => '1065597',
                'mobileNumber'             => '306900000001',
                'status'                   => DeliveryStatus::DELIVERED,
            ],
            [
                'smsServerRequestId'       => 'cc479845-0f15-4ae7-b645-6ef6489868c1',
                'clientRequestId'          => '20240316203045',
                'internalSmsOutDeliveryId' => '1065598',
                'mobileNumber'             => '306900000002',
                'status'                   => DeliveryStatus::SENT,
            ],
        ];

        $client = $this->createMockClient([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($responsePayload)),
        ], $history);

        $statuses = $client->checkStatusByServerRequestId('cc479845-0f15-4ae7-b645-6ef6489868c1');

        $this->assertCount(2, $statuses);
        $this->assertContainsOnlyInstancesOf(SmsDeliveryStatusItem::class, $statuses);

        $this->assertSame('cc479845-0f15-4ae7-b645-6ef6489868c1', $statuses[0]->getSmsServerRequestId());
        $this->assertSame('20240316203045', $statuses[0]->getClientRequestId());
        $this->assertSame('1065597', $statuses[0]->getInternalSmsOutDeliveryId());
        $this->assertSame('306900000001', $statuses[0]->getMobileNumber());
        $this->assertSame(DeliveryStatus::DELIVERED, $statuses[0]->getStatus());
        $this->assertTrue($statuses[0]->isDelivered());
        $this->assertTrue($statuses[0]->isFinalStatus());

        $this->assertFalse($statuses[1]->isDelivered());
        $this->assertFalse($statuses[1]->isFinalStatus());

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame(
            'https://api.example.invalid/v1/check_status?smsServerRequestId=cc479845-0f15-4ae7-b645-6ef6489868c1',
            (string) $request->getUri()
        );
    }

    public function testCheckStatusByClientRequestId(): void
    {
        $history = [];
        $responsePayload = [
            [
                'smsServerRequestId'       => 'cc479845-0f15-4ae7-b645-6ef6489868c1',
                'clientRequestId'          => 'client-123',
                'internalSmsOutDeliveryId' => '1065597',
                'mobileNumber'             => '306900000001',
                'status'                   => DeliveryStatus::NEW,
            ],
        ];

        $client = $this->createMockClient([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($responsePayload)),
        ], $history);

        $statuses = $client->checkStatusByClientRequestId('client-123');

        $this->assertCount(1, $statuses);
        $this->assertSame('client-123', $statuses[0]->getClientRequestId());

        $request = $history[0]['request'];
        $this->assertSame(
            'https://api.example.invalid/v1/check_status?clientRequestId=client-123',
            (string) $request->getUri()
        );
    }

    public function testCheckStatusThrowsWhenNeitherIdentifierProvided(): void
    {
        $client = $this->createMockClient([]);

        $this->expectException(RayoException::class);
        $this->expectExceptionMessage("Either 'smsServerRequestId' or 'clientRequestId' must be provided.");

        $client->checkStatus();
    }

    public function testCheckStatusThrowsWhenBothIdentifiersProvided(): void
    {
        $client = $this->createMockClient([]);

        $this->expectException(RayoException::class);
        $this->expectExceptionMessage("Provide either 'smsServerRequestId' or 'clientRequestId', not both.");

        $client->checkStatus('server-id', 'client-id');
    }

    public function testDeleteSchedule(): void
    {
        $history = [];
        $scheduleId = 'e0b107da-669a-40f5-b4d0-24fc70409307';
        $responsePayload = [
            'result' => [
                'username'             => 'demo_user',
                'resultCode'           => '1',
                'resultDescription'    => 'SUCCESS',
                'smsRequestScheduleId' => $scheduleId,
            ],
        ];

        $client = $this->createMockClient([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($responsePayload)),
        ], $history);

        $response = $client->deleteSchedule($scheduleId);

        $this->assertInstanceOf(DeleteScheduleResponse::class, $response);
        $this->assertTrue($response->isSuccess());
        $this->assertSame('demo_user', $response->getUsername());
        $this->assertSame('1', $response->getResultCode());
        $this->assertSame('SUCCESS', $response->getResultDescription());
        $this->assertSame($scheduleId, $response->getSmsRequestScheduleId());

        $request = $history[0]['request'];
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertSame(
            'https://api.example.invalid/v1/sms_request_schedules/' . $scheduleId,
            (string) $request->getUri()
        );
    }

    public function testDeleteScheduleRejectsEmptyId(): void
    {
        $client = $this->createMockClient([]);

        $this->expectException(RayoException::class);
        $this->expectExceptionMessage('Schedule ID cannot be empty.');

        $client->deleteSchedule('   ');
    }

    public function testError422UnprocessableEntityParsesStructuredDetails(): void
    {
        $errorBody = [
            'result' => [
                'username'             => 'demo_user',
                'resultCode'           => '0',
                'resultDescription'    => 'ERROR: From label does not belong to User',
                'clientRequestId'      => 'req-1',
                'smsServerRequestId'   => null,
                'smsRequestScheduleId' => null,
            ],
            'error' => 'Cannot save the SMS Request',
        ];

        $client = $this->createMockClient([
            new Response(422, ['Content-Type' => 'application/json'], (string) json_encode($errorBody)),
        ]);

        try {
            $client->sendSms([
                'fromLabel'      => 'UNKNOWN',
                'toMobileNumber' => '306900000001',
                'smsBody'        => 'Test',
            ]);
            $this->fail('Expected RayoException to be thrown.');
        } catch (RayoException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertSame('Cannot save the SMS Request', $e->getErrorMessage());
            $this->assertSame('0', $e->getResultCode());
            $this->assertSame('ERROR: From label does not belong to User', $e->getResultDescription());
            $this->assertStringContainsString('Cannot save the SMS Request', $e->getMessage());
            $this->assertStringContainsString('ERROR: From label does not belong to User', $e->getMessage());
            $this->assertIsArray($e->getErrorPayload());
        }
    }

    public function testError401UnauthorizedWithEmptyBody(): void
    {
        $client = $this->createMockClient([
            new Response(401, [], ''),
        ]);

        try {
            $client->getBalance();
            $this->fail('Expected RayoException to be thrown.');
        } catch (RayoException $e) {
            $this->assertSame(401, $e->getStatusCode());
            $this->assertStringContainsString('Unauthorized (401)', $e->getMessage());
        }
    }

    public function testError404NotFoundOnCheckStatus(): void
    {
        $client = $this->createMockClient([
            new Response(404, ['Content-Type' => 'application/json'], (string) json_encode([
                'error' => 'No record(s) found',
            ])),
        ]);

        try {
            $client->checkStatusByClientRequestId('unknown-id');
            $this->fail('Expected RayoException to be thrown.');
        } catch (RayoException $e) {
            $this->assertSame(404, $e->getStatusCode());
            $this->assertSame('No record(s) found', $e->getErrorMessage());
        }
    }
}
