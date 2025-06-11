<?php

namespace App\DTOs\Services\Responses;

use JsonSerializable;

/**
 * Standard response DTO for service layer methods.
 */
class BaseServiceResponseDTO implements JsonSerializable
{
    /**
     * @param boolean $success
     * @param string $message
     * @param mixed $data
     * @param integer $statusCode
     */
    public function __construct(
        public bool $success,
        public string $message = '',
        public mixed $data = null,
        public int $statusCode
    ) {
    }

    /**
     * Create a successful response instance.
     *
     * @param string $message
     * @param mixed $data
     * @param integer $statusCode
     * @return static
     */
    public static function success(string $message = 'OK', mixed $data = null, int $statusCode = 200): static
    {
        return new static(true, $message, $data, $statusCode);
    }

    /**
     * Create a failure response instance.
     *
     * @param string $message
     * @param mixed $data
     * @param integer $statusCode
     * @return static
     */
    public static function failure(string $message, mixed $data = null, int $statusCode = 400): static
    {
        return new static(false, $message, $data, $statusCode);
    }

    /**
     * Specifies data which should be serialized to JSON.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'statusCode' => $this->statusCode,
            'data' => $this->data,
        ];
    }
}
