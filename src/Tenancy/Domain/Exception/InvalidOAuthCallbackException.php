<?php

declare(strict_types=1);

namespace App\Tenancy\Domain\Exception;

final class InvalidOAuthCallbackException extends \RuntimeException
{
    public static function hmacMismatch(): self
    {
        return new self('HMAC validation failed.');
    }

    public static function invalidState(): self
    {
        return new self('OAuth state parameter is invalid or expired.');
    }

    public static function shopDomainMismatch(): self
    {
        return new self('Shop domain does not match the one stored in the OAuth state.');
    }
}
