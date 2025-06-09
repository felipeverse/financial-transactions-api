<?php

namespace App\DTOs\Services\Transaction;

use App\Models\Wallet;

/**
 * Data Transfer Object representing the response of a deposit transaction.
 *
 * Encapsulates the result status, the updated wallet on success,
 * and error information on failure.
 */
class DepositResponse
{
    public function __construct(
        public bool $success,
        public ?Wallet $wallet = null,
        public ?string $error = null,
        public ?int $code = null
    ) {}

    /**
     * Create a successful deposit response.
     *
     * @param Wallet $wallet The wallet updated after deposit.
     * @return self
     */
    public static function success(Wallet $wallet): self
    {
        return new self(true, $wallet);
    }

    /**
     * Create a failure deposit response.
     *
     * @param string $error Error message describing the failure.
     * @param int $code Optional HTTP or internal error code (default: 400).
     * @return self
     */
    public static function failure(string $error, int $code = 400): self
    {
        return new self(false, null, $error, $code);
    }
}
