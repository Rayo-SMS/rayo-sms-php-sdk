<?php

declare(strict_types=1);

namespace Rayo\SmsSdk\Exceptions;

use Exception;
use Throwable;

class RayoException extends Exception
{
    private ?int $statusCode;
    private ?array $errorPayload;
    private ?string $rawResponseBody;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        ?int $statusCode = null,
        ?array $errorPayload = null,
        ?string $rawResponseBody = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->statusCode = $statusCode ?? ($code > 0 ? $code : null);
        $this->errorPayload = $errorPayload;
        $this->rawResponseBody = $rawResponseBody;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getErrorPayload(): ?array
    {
        return $this->errorPayload;
    }

    public function getRawResponseBody(): ?string
    {
        return $this->rawResponseBody;
    }

    public function getErrorMessage(): ?string
    {
        if (isset($this->errorPayload['error']) && is_string($this->errorPayload['error'])) {
            return $this->errorPayload['error'];
        }

        return null;
    }

    public function getResultCode(): ?string
    {
        if (isset($this->errorPayload['result']['resultCode'])) {
            return (string) $this->errorPayload['result']['resultCode'];
        }

        return null;
    }

    public function getResultDescription(): ?string
    {
        if (isset($this->errorPayload['result']['resultDescription']) && is_string($this->errorPayload['result']['resultDescription'])) {
            return $this->errorPayload['result']['resultDescription'];
        }

        return null;
    }
}

