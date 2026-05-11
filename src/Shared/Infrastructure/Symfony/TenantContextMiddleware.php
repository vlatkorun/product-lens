<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony;

use App\Shared\Infrastructure\Tenancy\TenantContext;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class TenantContextMiddleware implements MiddlewareInterface
{
    public function __construct(private TenantContext $tenantContext)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $stamp = $envelope->last(TenantStamp::class);

        if ($stamp instanceof TenantStamp) {
            $this->tenantContext->activate($stamp->tenantId);
        }

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            if ($stamp instanceof TenantStamp) {
                $this->tenantContext->deactivate();
            }
        }
    }
}
