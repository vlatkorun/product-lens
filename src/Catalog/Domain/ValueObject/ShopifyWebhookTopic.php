<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

enum ShopifyWebhookTopic: string
{
    case ProductsCreate = 'products/create';
    case ProductsUpdate = 'products/update';
    case ProductsDelete = 'products/delete';

    public function isFor(string $prefix): bool
    {
        return \str_starts_with($this->value, $prefix.'/');
    }
}
