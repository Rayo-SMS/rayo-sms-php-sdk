<?php

declare(strict_types=1);

namespace Rayo\SmsSdk\Models;

use ArrayAccess;
use JsonSerializable;
use Rayo\SmsSdk\Exceptions\RayoException;

class BalanceResponse implements ArrayAccess, JsonSerializable
{
    private string $requestId;
    private float $credits;
    /** @var array<string, mixed> */
    private array $rawData;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->rawData = $data;
        $this->requestId = isset($data['request_id']) ? (string) $data['request_id'] : '';
        $this->credits = isset($data['balance']['credits']) ? (float) $data['balance']['credits'] : 0.0;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function getCredits(): float
    {
        return $this->credits;
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
        throw new RayoException('BalanceResponse is immutable.');
    }

    /**
     * @param mixed $offset
     * @throws RayoException
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset): void
    {
        throw new RayoException('BalanceResponse is immutable.');
    }
}
