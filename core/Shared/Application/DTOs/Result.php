<?php

declare(strict_types=1);

namespace Core\Shared\Application\DTOs;

/**
 * Success/failure envelope returned by EVERY Service use-case method.
 * errorCode holds DOMAIN-SPECIFIC ERROR KEYS (e.g. CATEGORY_SLUG_CONFLICT),
 * never raw HTTP statuses — Delivery translates keys to status codes.
 */
readonly class Result
{
    private function __construct(
        public bool $success,
        public mixed $data,
        public string $message,
        public ?string $errorCode,
    ) {}

    public static function success(mixed $data = null, string $message = ''): self
    {
        return new self(success: true, data: $data, message: $message, errorCode: null);
    }

    public static function failure(string $message, ?string $errorCode = null): self
    {
        return new self(success: false, data: null, message: $message, errorCode: $errorCode);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return !$this->success;
    }

    /**
     * Get the success data payload.
     *
     * @return mixed
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Get the result message.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }
}
