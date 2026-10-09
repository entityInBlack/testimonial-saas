<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a public submission hits the 6th-rejection cap of either
 * the per-IP or per-email throttle. The message is intentionally generic
 * — it does not reveal which key was exhausted, the limit number, or the
 * window. It is the same on every reject.
 */
class SubmissionThrottledException extends RuntimeException
{
    public function __construct(string $message = 'Too many submissions. Try again later.')
    {
        parent::__construct($message);
    }
}
