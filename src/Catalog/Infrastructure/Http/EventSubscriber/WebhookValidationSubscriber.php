<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\EventSubscriber;

use App\Catalog\Infrastructure\Http\Attribute\ValidateShopifySignature;
use App\Catalog\Infrastructure\Http\Attribute\ValidateShopifyTopic;
use App\Catalog\Infrastructure\Http\Validator\WebhookSignatureValidator;
use App\Catalog\Infrastructure\Http\Validator\WebhookTenantValidator;
use App\Catalog\Infrastructure\Http\Validator\WebhookTopicValidator;
use App\Tenancy\Domain\Repository\TenantRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class WebhookValidationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private WebhookTenantValidator $tenantValidator,
        private WebhookSignatureValidator $signatureValidator,
        private WebhookTopicValidator $topicValidator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => 'onKernelController'];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($event->getAttributes(ValidateShopifySignature::class) !== []) {
            $shopDomain = $request->headers->get('X-Shopify-Shop-Domain', '');
            $tenant = $this->tenantRepository->findByShopDomain($shopDomain);
            $webhookSecret = $tenant?->shopifyWebhookSecret();

            if ($webhookSecret === null
                || !$this->tenantValidator->validate((string) $request->attributes->get('tenantId', ''), $tenant->id()->toRfc4122())
                || !$this->signatureValidator->validate($request, $webhookSecret)
            ) {
                $event->setController(static fn () => new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED));

                return;
            }
        }

        foreach ($event->getAttributes(ValidateShopifyTopic::class) as $attribute) {
            if (!$this->topicValidator->validate($request, $attribute->topicPrefix)) {
                $event->setController(static fn () => new JsonResponse(['error' => 'Unprocessable Content'], Response::HTTP_UNPROCESSABLE_ENTITY));

                return;
            }
        }
    }
}
