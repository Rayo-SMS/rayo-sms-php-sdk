<?php

declare(strict_types=1);

namespace Rayo\SmsSdk\Tests;

use PHPUnit\Framework\TestCase;
use Rayo\SmsSdk\Exceptions\RayoException;
use Rayo\SmsSdk\Models\BalanceResponse;
use Rayo\SmsSdk\Models\CallbackPayload;
use Rayo\SmsSdk\Models\DeleteScheduleResponse;
use Rayo\SmsSdk\Models\DeliveryStatus;
use Rayo\SmsSdk\Models\SmsDeliveryStatusItem;
use Rayo\SmsSdk\Models\SmsRequest;
use Rayo\SmsSdk\Models\SmsResponse;

class ModelsTest extends TestCase
{
    public function testSmsRequestRequiresFromLabel(): void
    {
        $this->expectException(RayoException::class);
        $this->expectExceptionMessage("The 'fromLabel' field is required.");

        new SmsRequest('', 'Hello', '306900000001');
    }

    public function testSmsRequestRequiresSmsBody(): void
    {
        $this->expectException(RayoException::class);
        $this->expectExceptionMessage("The 'smsBody' field is required.");

        new SmsRequest('SENDER', '   ', '306900000001');
    }

    public function testSmsRequestEnforcesMaximumLength(): void
    {
        $this->expectException(RayoException::class);
        $this->expectExceptionMessage("The 'smsBody' field cannot exceed 1000 characters.");

        new SmsRequest('SENDER', str_repeat('a', 1001), '306900000001');
    }

    public function testSmsRequestRequiresMobileNumberOrGroup(): void
    {
        $this->expectException(RayoException::class);
        $this->expectExceptionMessage("Either 'toMobileNumber' or 'toGroup' must be provided.");

        new SmsRequest('SENDER', 'Hello world');
    }

    public function testSmsRequestAllowsGroupOnly(): void
    {
        $request = SmsRequest::fromArray([
            'fromLabel' => 'SENDER',
            'smsBody'   => 'Hello Group!',
            'toGroup'   => 'High Valued Customers;Promotion Customers',
        ]);

        $this->assertNull($request->getToMobileNumber());
        $this->assertSame('High Valued Customers;Promotion Customers', $request->getToGroup());
        $this->assertSame([
            'fromLabel' => 'SENDER',
            'smsBody'   => 'Hello Group!',
            'toGroup'   => 'High Valued Customers;Promotion Customers',
        ], $request->toArray());
    }

    public function testDeliveryStatusConstantsAndFinalCheck(): void
    {
        $this->assertCount(9, DeliveryStatus::all());
        $this->assertTrue(DeliveryStatus::isFinal(DeliveryStatus::DELIVERED));
        $this->assertTrue(DeliveryStatus::isFinal(DeliveryStatus::NOT_DELIVERED));
        $this->assertTrue(DeliveryStatus::isFinal(DeliveryStatus::UNKNOWN_DELIVERY));
        $this->assertTrue(DeliveryStatus::isFinal(DeliveryStatus::FAILURE));
        $this->assertTrue(DeliveryStatus::isFinal(DeliveryStatus::PROCESSING_ERROR));

        $this->assertFalse(DeliveryStatus::isFinal(DeliveryStatus::NEW));
        $this->assertFalse(DeliveryStatus::isFinal(DeliveryStatus::TEMP_LOCKED));
        $this->assertFalse(DeliveryStatus::isFinal(DeliveryStatus::SENT));
        $this->assertFalse(DeliveryStatus::isFinal(DeliveryStatus::ERROR));
    }

    public function testCallbackPayloadFromJsonAndQueryString(): void
    {
        $json = (string) json_encode([
            'smsServerRequestId'         => '1396522',
            'clientRequestId'            => '20240316203045',
            'internalSmsOutDeliveryId'   => '1065597',
            'senderId'                   => 'SENDERID',
            'mobileNumber'               => '306900000001',
            'status'                     => 'delivered',
            'errorDescription'           => 'no error',
            'requestTimestamp'           => '2026-10-21T10:00:00Z',
            'lastStatusUpdatedTimestamp' => '2026-10-21T10:00:05Z',
        ]);

        $payload = CallbackPayload::fromJson($json);
        $this->assertSame('1396522', $payload->getSmsServerRequestId());
        $this->assertSame('20240316203045', $payload->getClientRequestId());
        $this->assertSame('1065597', $payload->getInternalSmsOutDeliveryId());
        $this->assertSame('SENDERID', $payload->getSenderId());
        $this->assertSame('306900000001', $payload->getMobileNumber());
        $this->assertSame('delivered', $payload->getStatus());
        $this->assertSame('no error', $payload->getErrorDescription());
        $this->assertSame('2026-10-21T10:00:00Z', $payload->getRequestTimestamp());
        $this->assertSame('2026-10-21T10:00:05Z', $payload->getLastStatusUpdatedTimestamp());
        $this->assertTrue($payload->isDelivered());
        $this->assertTrue($payload->isFinalStatus());

        $query = 'clientRequestId=20240316203045&errorDescription=no+error&internalSmsOutDeliveryId=1065597&lastStatusUpdatedTimestamp=2026-10-21T10%3A00%3A05Z&mobileNumber=306900000001&requestedTimestamp=2026-10-21T10%3A00%3A00Z&senderId=SENDERID&smsServerRequestId=1396522&status=sent';
        $fromQuery = CallbackPayload::fromQueryString($query);
        $this->assertSame('sent', $fromQuery->getStatus());
        $this->assertSame('2026-10-21T10:00:00Z', $fromQuery->getRequestTimestamp());
        $this->assertFalse($fromQuery->isDelivered());
    }

    public function testImmutabilityOnResponseModels(): void
    {
        $smsResponse = new SmsResponse(['result' => ['resultCode' => '1', 'resultDescription' => 'SUCCESS']]);
        $balanceResponse = new BalanceResponse(['request_id' => '1', 'balance' => ['credits' => 10]]);
        $statusItem = new SmsDeliveryStatusItem(['status' => 'sent']);
        $deleteResponse = new DeleteScheduleResponse(['result' => ['resultCode' => '1', 'resultDescription' => 'SUCCESS']]);

        $this->expectException(RayoException::class);
        $smsResponse['result'] = [];
        unset($balanceResponse['request_id'], $statusItem['status'], $deleteResponse['result']);
    }
}
