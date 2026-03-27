<?php

namespace App\Services\Auth;

use RuntimeException;
use Throwable;

class EmailVerificationDeliveryException extends RuntimeException
{
    public static function fromTransport(Throwable $previous): self
    {
        return new self(
            'We could not send the verification email right now. Please try again in a moment.',
            previous: $previous,
        );
    }
}
