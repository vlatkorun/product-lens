<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\Validator;

final readonly class WebhookTenantValidator
{
    public function validate(string $routeTenantId, string $resolvedTenantId): bool
    {
        return $routeTenantId === $resolvedTenantId;
    }
}
