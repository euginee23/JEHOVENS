<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * PayMongo refused a request, or could not be reached at all.
 *
 * Always caught at the booking page: a guest who cannot be sent to checkout is told to
 * try again, and the reservation held for them is removed rather than left sitting on
 * dates nobody paid for.
 */
class PayMongoException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $errors  whatever PayMongo said was wrong
     */
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    /**
     * Build one from a failed response body.
     *
     * @param  array<string, mixed>  $body
     */
    public static function fromResponse(int $status, array $body): self
    {
        $errors = data_get($body, 'errors', []);
        $detail = data_get($errors, '0.detail');

        return new self(
            $detail ? "PayMongo rejected the request: {$detail}" : "PayMongo returned HTTP {$status}.",
            $status,
            is_array($errors) ? $errors : [],
        );
    }
}
