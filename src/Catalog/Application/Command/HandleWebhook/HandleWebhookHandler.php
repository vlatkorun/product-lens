<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\HandleWebhook;

use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Domain\Service\ProductFetcherInterface;
use App\Catalog\Domain\ValueObject\ShopifyGid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\UuidV7;

#[AsMessageHandler]
final readonly class HandleWebhookHandler
{
    public function __construct(
        private ProductFetcherInterface $productFetcher,
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    public function __invoke(HandleWebhookCommand $command): void
    {
        $gid = ShopifyGid::fromString($command->shopifyProductGid);
        $tenantId = UuidV7::fromString($command->tenantId);

        if ($command->eventType === 'products/delete') {
            $this->productRepository->remove($gid, $tenantId);

            return;
        }

        $product = $this->productFetcher->fetchByGid($gid, $tenantId);
        $this->productRepository->upsert($product);
    }
}
