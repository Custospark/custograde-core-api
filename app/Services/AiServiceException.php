<?php

namespace App\Services;

/**
 * Raised when the AI service cannot be used.
 *
 * `retryable` is the distinction that matters operationally. A rate limit or a
 * timeout should go back on the queue; a malformed scan will never succeed and
 * must reach a human instead of looping forever (CAP-09).
 */
class AiServiceException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = false,
    ) {
        parent::__construct($message);
    }
}
