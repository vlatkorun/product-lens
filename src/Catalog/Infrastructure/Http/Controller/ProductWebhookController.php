<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\Controller;

use App\Catalog\Application\Command\HandleWebhook\HandleWebhookCommand;
use App\Catalog\Domain\ValueObject\ShopifyGid;
use App\Catalog\Domain\ValueObject\ShopifyWebhookTopic;
use App\Catalog\Infrastructure\Http\Attribute\ValidateShopifySignature;
use App\Catalog\Infrastructure\Http\Attribute\ValidateShopifyTopic;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ProductWebhookController
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {
    }

    #[Route('/webhooks/shopify/{tenantId}/products', name: 'shopify_webhook_products', methods: ['POST'])]
    #[ValidateShopifySignature]
    #[ValidateShopifyTopic('products')]
    public function handle(Request $request, string $tenantId): JsonResponse
    {
        $topic = ShopifyWebhookTopic::from($request->headers->get('X-Shopify-Topic', ''));
        $payload = \json_decode((string) $request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $shopifyObjectId = ShopifyGid::product($payload['id'])->value;

        $this->commandBus->dispatch(new HandleWebhookCommand($tenantId, $shopifyObjectId, $topic, $payload));

        return new JsonResponse(null, Response::HTTP_OK);
    }
}
