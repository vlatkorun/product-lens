<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

final class UserAlreadyExistsException extends \DomainException
{
    public static function forEmail(string $email): self
    {
        return new self(\sprintf('A user with email "%s" already exists.', $email));
    }
}
