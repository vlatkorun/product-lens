<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

final class UserNotFoundException extends \DomainException
{
    public static function forEmail(string $email): self
    {
        return new self(\sprintf('User with email "%s" not found.', $email));
    }

    public static function forId(string $id): self
    {
        return new self(\sprintf('User with id "%s" not found.', $id));
    }
}
