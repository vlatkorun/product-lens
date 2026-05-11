<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
