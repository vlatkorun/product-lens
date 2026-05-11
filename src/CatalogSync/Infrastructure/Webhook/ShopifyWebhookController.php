<?php

declare(strict_types=1);

namespace App\CatalogSync\Infrastructure\Webhook;

use App\CatalogSync\Application\Command\HandleWebhook\HandleWebhookCommand;
use App\CatalogSync\Domain\ValueObject\ShopifyGid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ShopifyWebhookController
{
    public function __construct(
        private ShopifyWebhookValidator $validator,
        private MessageBusInterface $commandBus,
    ) {}

    #[Route('/webhooks/shopify/{tenantId}/products', name: 'shopify_webhook_products', methods: ['POST'])]
    public function products(Request $request, string $tenantId): JsonResponse
    {
        if (!$this->validator->validate($request)) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $topic = $request->headers->get('X-Shopify-Topic', '');
        $payload = \json_decode((string) $request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $shopifyProductGid = ShopifyGid::product($payload['id'])->value;

        $this->commandBus->dispatch(new HandleWebhookCommand($tenantId, $shopifyProductGid, $topic));

        return new JsonResponse(null, Response::HTTP_OK);
    }
}
