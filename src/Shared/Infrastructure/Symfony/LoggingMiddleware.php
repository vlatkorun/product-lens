<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

final readonly class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $consuming = $envelope->last(ReceivedStamp::class) !== null;

        if (!$consuming) {
            return $stack->next()->handle($envelope, $stack);
        }

        $context = $this->context($envelope);

        $this->logger->info('Message received', $context);

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
            $this->logger->info('Message handled', $context);

            return $envelope;
        } catch (\Throwable $e) {
            $this->logger->error('Message failed', $context + [
                'exception'       => $e->getMessage(),
                'exception_class' => $e::class,
                'file'            => $e->getFile(),
                'line'            => $e->getLine(),
            ]);

            throw $e;
        }
    }

    /** @return array<string, string> */
    private function context(Envelope $envelope): array
    {
        $class = $envelope->getMessage()::class;
        $shortName = \strrchr($class, '\\');
        $context = ['message' => $shortName !== false ? \substr($shortName, 1) : $class];

        $stamp = $envelope->last(TenantStamp::class);
        if ($stamp instanceof TenantStamp) {
            $context['tenant_id'] = $stamp->tenantId->toRfc4122();
        }

        return $context;
    }
}
