<?php

declare(strict_types=1);

namespace Rayo\SmsSdk\Models;

use ArrayAccess;
use JsonSerializable;
use Rayo\SmsSdk\Exceptions\RayoException;

class SmsDeliveryStatusItem implements ArrayAccess, JsonSerializable
{
    private string $smsServerRequestId;
    private ?string $clientRequestId;
    private string $internalSmsOutDeliveryId;
    private string $mobileNumber;
    private string $status;
    /** @var array<string, mixed> */
    private array $rawData;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->rawData = $data;
        $this->smsServerRequestId = isset($data['smsServerRequestId']) ? (string) $data['smsServerRequestId'] : '';
        $this->clientRequestId = isset($data['clientRequestId']) ? (string) $data['clientRequestId'] : null;
        $this->internalSmsOutDeliveryId = isset($data['internalSmsOutDeliveryId']) ? (string) $data['internalSmsOutDeliveryId'] : '';
        $this->mobileNumber = isset($data['mobileNumber']) ? (string) $data['mobileNumber'] : '';
        $this->status = isset($data['status']) ? (string) $data['status'] : '';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function getSmsServerRequestId(): string
    {
        return $this->smsServerRequestId;
    }

    public function getClientRequestId(): ?string
    {
        return $this->clientRequestId;
    }

    public function getInternalSmsOutDeliveryId(): string
    {
        return $this->internalSmsOutDeliveryId;
    }

    public function getMobileNumber(): string
    {
        return $this->mobileNumber;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isDelivered(): bool
    {
        return $this->status === DeliveryStatus::DELIVERED;
    }

    public function isFinalStatus(): bool
    {
        return DeliveryStatus::isFinal($this->status);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->rawData;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return $this->rawData;
    }

    /**
     * @param mixed $offset
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->rawData);
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->rawData[$offset] ?? null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @throws RayoException
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value): void
    {
        throw new RayoException('SmsDeliveryStatusItem is immutable.');
    }

    /**
     * @param mixed $offset
     * @throws RayoException
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset): void
    {
        throw new RayoException('SmsDeliveryStatusItem is immutable.');
    }
}
