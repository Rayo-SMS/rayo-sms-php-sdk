<?php

declare(strict_types=1);

namespace Rayo\SmsSdk\Models;

use ArrayAccess;
use JsonSerializable;
use Rayo\SmsSdk\Exceptions\RayoException;

class SmsResponse implements ArrayAccess, JsonSerializable
{
    private ?string $username;
    private string $resultCode;
    private string $resultDescription;
    private ?string $clientRequestId;
    private ?string $smsServerRequestId;
    private ?string $smsRequestScheduleId;
    private ?string $error;
    /** @var array<string, mixed> */
    private array $rawData;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->rawData = $data;
        $result = isset($data['result']) && is_array($data['result']) ? $data['result'] : $data;

        $this->username = isset($result['username']) ? (string) $result['username'] : null;
        $this->resultCode = isset($result['resultCode']) ? (string) $result['resultCode'] : '';
        $this->resultDescription = isset($result['resultDescription']) ? (string) $result['resultDescription'] : '';
        $this->clientRequestId = isset($result['clientRequestId']) ? (string) $result['clientRequestId'] : null;
        $this->smsServerRequestId = isset($result['smsServerRequestId']) ? (string) $result['smsServerRequestId'] : null;
        $this->smsRequestScheduleId = isset($result['smsRequestScheduleId']) ? (string) $result['smsRequestScheduleId'] : null;
        $this->error = isset($data['error']) ? (string) $data['error'] : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function isSuccess(): bool
    {
        return $this->resultCode === '1' && $this->resultDescription === 'SUCCESS';
    }

    public function isScheduled(): bool
    {
        return $this->smsRequestScheduleId !== null && $this->smsRequestScheduleId !== '';
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getResultCode(): string
    {
        return $this->resultCode;
    }

    public function getResultDescription(): string
    {
        return $this->resultDescription;
    }

    public function getClientRequestId(): ?string
    {
        return $this->clientRequestId;
    }

    public function getSmsServerRequestId(): ?string
    {
        return $this->smsServerRequestId;
    }

    public function getSmsRequestScheduleId(): ?string
    {
        return $this->smsRequestScheduleId;
    }

    public function getError(): ?string
    {
        return $this->error;
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
        throw new RayoException('SmsResponse is immutable.');
    }

    /**
     * @param mixed $offset
     * @throws RayoException
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset): void
    {
        throw new RayoException('SmsResponse is immutable.');
    }
}
