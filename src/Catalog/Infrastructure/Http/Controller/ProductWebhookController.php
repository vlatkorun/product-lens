<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\Controller;

use App\Catalog\Application\Command\HandleWebhook\HandleProductCreateWebhook\HandleProductCreateWebhookCommand;
use App\Catalog\Application\Command\HandleWebhook\HandleProductDeleteWebhook\HandleProductDeleteWebhookCommand;
use App\Catalog\Application\Command\HandleWebhook\HandleProductUpdateWebhook\HandleProductUpdateWebhookCommand;
use App\Catalog\Domain\ValueObject\ShopifyWebhookTopic;
use App\Catalog\Infrastructure\Http\Attribute\ValidateShopifySignature;
use App\Catalog\Infrastructure\Http\Attribute\ValidateShopifyTopic;
use App\Catalog\Infrastructure\Http\Product\Dto\ProductWebhookPayloadDto;
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
        $payload = ProductWebhookPayloadDto::fromArray(
            \json_decode((string) $request->getContent(), true, 512, \JSON_THROW_ON_ERROR),
        );

        $command = match ($topic) {
            ShopifyWebhookTopic::ProductsCreate => new HandleProductCreateWebhookCommand($tenantId, $payload),
            ShopifyWebhookTopic::ProductsUpdate => new HandleProductUpdateWebhookCommand($tenantId, $payload),
            ShopifyWebhookTopic::ProductsDelete => new HandleProductDeleteWebhookCommand($tenantId, $payload),
        };

        $this->commandBus->dispatch($command);

        return new JsonResponse(null, Response::HTTP_OK);
    }
}
