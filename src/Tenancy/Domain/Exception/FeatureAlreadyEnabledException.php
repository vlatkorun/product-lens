<?php

declare(strict_types=1);

namespace App\Tenancy\Domain\Exception;

use App\Tenancy\Domain\ValueObject\FeatureFlag;

final class FeatureAlreadyEnabledException extends \DomainException
{
    public static function forFlag(FeatureFlag $flag): self
    {
        return new self(sprintf('Feature "%s" is already enabled for this tenant.', $flag->value));
    }
}
