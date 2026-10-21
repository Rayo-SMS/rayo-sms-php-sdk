<?php

declare(strict_types=1);

namespace Rayo\SmsSdk\Models;

final class DeliveryStatus
{
    public const NEW = 'new';
    public const TEMP_LOCKED = 'temp_locked';
    public const PROCESSING_ERROR = 'processing_error';
    public const FAILURE = 'failure';
    public const SENT = 'sent';
    public const ERROR = 'error';
    public const DELIVERED = 'delivered';
    public const NOT_DELIVERED = 'not_delivered';
    public const UNKNOWN_DELIVERY = 'unknown_delivery';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::NEW,
            self::TEMP_LOCKED,
            self::PROCESSING_ERROR,
            self::FAILURE,
            self::SENT,
            self::ERROR,
            self::DELIVERED,
            self::NOT_DELIVERED,
            self::UNKNOWN_DELIVERY,
        ];
    }

    public static function isFinal(string $status): bool
    {
        return in_array($status, [
            self::PROCESSING_ERROR,
            self::FAILURE,
            self::DELIVERED,
            self::NOT_DELIVERED,
            self::UNKNOWN_DELIVERY,
        ], true);
    }
}
