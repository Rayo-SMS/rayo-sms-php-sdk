<?php

declare(strict_types=1);

namespace Rayo\SmsSdk\Models;

use DateTimeInterface;
use JsonSerializable;
use Rayo\SmsSdk\Exceptions\RayoException;

class SmsRequest implements JsonSerializable
{
    public const MAXIMUM_SMS_LENGTH = 1000;

    private string $fromLabel;
    private string $smsBody;
    private ?string $toMobileNumber;
    private ?string $toGroup;
    private ?string $clientRequestId;
    private ?bool $callbackWhenProcessedFlag;
    private ?bool $simulateSendFlag;
    private ?bool $concatFlag;
    private ?string $scheduledAt;

    /**
     * @param string|string[]|null $toMobileNumber
     * @param string|string[]|null $toGroup
     * @param DateTimeInterface|string|null $scheduledAt
     * @throws RayoException
     */
    public function __construct(
        string $fromLabel,
        string $smsBody,
        $toMobileNumber = null,
        $toGroup = null,
        ?string $clientRequestId = null,
        ?bool $callbackWhenProcessedFlag = null,
        ?bool $simulateSendFlag = null,
        ?bool $concatFlag = null,
        $scheduledAt = null
    ) {
        $this->fromLabel = trim($fromLabel);
        $this->smsBody = $smsBody;
        $this->toMobileNumber = self::normalizeMobileNumbers($toMobileNumber);
        $this->toGroup = self::normalizeGroups($toGroup);
        $this->clientRequestId = $clientRequestId;
        $this->callbackWhenProcessedFlag = $callbackWhenProcessedFlag;
        $this->simulateSendFlag = $simulateSendFlag;
        $this->concatFlag = $concatFlag;
        $this->scheduledAt = self::normalizeScheduledAt($scheduledAt);

        $this->validate();
    }

    /**
     * @param array<string, mixed> $data
     * @throws RayoException
     */
    public static function fromArray(array $data): self
    {
        $fromLabel = isset($data['fromLabel']) ? (string) $data['fromLabel'] : '';
        $smsBody = isset($data['smsBody']) ? (string) $data['smsBody'] : '';

        return new self(
            $fromLabel,
            $smsBody,
            $data['toMobileNumber'] ?? null,
            $data['toGroup'] ?? null,
            isset($data['clientRequestId']) ? (string) $data['clientRequestId'] : null,
            isset($data['callbackWhenProcessedFlag']) ? self::assertBool($data['callbackWhenProcessedFlag'], 'callbackWhenProcessedFlag') : null,
            isset($data['simulateSendFlag']) ? self::assertBool($data['simulateSendFlag'], 'simulateSendFlag') : null,
            isset($data['concatFlag']) ? self::assertBool($data['concatFlag'], 'concatFlag') : null,
            $data['scheduledAt'] ?? null
        );
    }

    /**
     * @throws RayoException
     */
    public function validate(): void
    {
        if ($this->fromLabel === '') {
            throw new RayoException("The 'fromLabel' field is required.");
        }

        if (trim($this->smsBody) === '') {
            throw new RayoException("The 'smsBody' field is required.");
        }

        $bodyLength = function_exists('mb_strlen')
            ? mb_strlen($this->smsBody, 'UTF-8')
            : strlen($this->smsBody);

        if ($bodyLength > self::MAXIMUM_SMS_LENGTH) {
            throw new RayoException(sprintf(
                "The 'smsBody' field cannot exceed %d characters.",
                self::MAXIMUM_SMS_LENGTH
            ));
        }

        $hasMobile = $this->toMobileNumber !== null && trim($this->toMobileNumber) !== '';
        $hasGroup = $this->toGroup !== null && trim($this->toGroup) !== '';

        if (!$hasMobile && !$hasGroup) {
            throw new RayoException("Either 'toMobileNumber' or 'toGroup' must be provided.");
        }
    }

    public function getFromLabel(): string
    {
        return $this->fromLabel;
    }

    public function getSmsBody(): string
    {
        return $this->smsBody;
    }

    public function getToMobileNumber(): ?string
    {
        return $this->toMobileNumber;
    }

    public function getToGroup(): ?string
    {
        return $this->toGroup;
    }

    public function getClientRequestId(): ?string
    {
        return $this->clientRequestId;
    }

    public function getCallbackWhenProcessedFlag(): ?bool
    {
        return $this->callbackWhenProcessedFlag;
    }

    public function getSimulateSendFlag(): ?bool
    {
        return $this->simulateSendFlag;
    }

    public function getConcatFlag(): ?bool
    {
        return $this->concatFlag;
    }

    public function getScheduledAt(): ?string
    {
        return $this->scheduledAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'fromLabel' => $this->fromLabel,
            'smsBody'   => $this->smsBody,
        ];

        if ($this->toMobileNumber !== null && $this->toMobileNumber !== '') {
            $payload['toMobileNumber'] = $this->toMobileNumber;
        }

        if ($this->toGroup !== null && $this->toGroup !== '') {
            $payload['toGroup'] = $this->toGroup;
        }

        if ($this->clientRequestId !== null) {
            $payload['clientRequestId'] = $this->clientRequestId;
        }

        if ($this->callbackWhenProcessedFlag !== null) {
            $payload['callbackWhenProcessedFlag'] = $this->callbackWhenProcessedFlag;
        }

        if ($this->simulateSendFlag !== null) {
            $payload['simulateSendFlag'] = $this->simulateSendFlag;
        }

        if ($this->concatFlag !== null) {
            $payload['concatFlag'] = $this->concatFlag;
        }

        if ($this->scheduledAt !== null && $this->scheduledAt !== '') {
            $payload['scheduledAt'] = $this->scheduledAt;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param mixed $toMobileNumber
     * @throws RayoException
     */
    private static function normalizeMobileNumbers($toMobileNumber): ?string
    {
        if ($toMobileNumber === null) {
            return null;
        }

        if (is_array($toMobileNumber)) {
            $cleaned = array_values(array_filter(array_map('trim', array_map('strval', $toMobileNumber)), static function (string $v): bool {
                return $v !== '';
            }));

            return $cleaned === [] ? null : implode(';', $cleaned);
        }

        if (is_string($toMobileNumber)) {
            $trimmed = trim($toMobileNumber);
            return $trimmed === '' ? null : $trimmed;
        }

        throw new RayoException("The 'toMobileNumber' field must be a string or an array of strings.");
    }

    /**
     * @param mixed $toGroup
     * @throws RayoException
     */
    private static function normalizeGroups($toGroup): ?string
    {
        if ($toGroup === null) {
            return null;
        }

        if (is_array($toGroup)) {
            $cleaned = array_values(array_filter(array_map('trim', array_map('strval', $toGroup)), static function (string $v): bool {
                return $v !== '';
            }));

            return $cleaned === [] ? null : implode(';', $cleaned);
        }

        if (is_string($toGroup)) {
            $trimmed = trim($toGroup);
            return $trimmed === '' ? null : $trimmed;
        }

        throw new RayoException("The 'toGroup' field must be a string or an array of strings.");
    }

    /**
     * @param mixed $scheduledAt
     * @throws RayoException
     */
    private static function normalizeScheduledAt($scheduledAt): ?string
    {
        if ($scheduledAt === null) {
            return null;
        }

        if ($scheduledAt instanceof DateTimeInterface) {
            return $scheduledAt->format(DateTimeInterface::ATOM);
        }

        if (is_string($scheduledAt)) {
            $trimmed = trim($scheduledAt);
            return $trimmed === '' ? null : $trimmed;
        }

        throw new RayoException("The 'scheduledAt' field must be an ISO-8601 string or DateTimeInterface.");
    }

    /**
     * @param mixed $value
     * @throws RayoException
     */
    private static function assertBool($value, string $fieldName): bool
    {
        if (!is_bool($value)) {
            throw new RayoException(sprintf("The '%s' field must be a boolean.", $fieldName));
        }

        return $value;
    }
}
