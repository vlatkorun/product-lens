<?php

declare(strict_types=1);

namespace App\Audit\Domain\Specification\ValueObject;

enum Severity: string
{
    case CRITICAL = 'CRITICAL';
    case WARNING = 'WARNING';
    case INFO = 'INFO';
}
