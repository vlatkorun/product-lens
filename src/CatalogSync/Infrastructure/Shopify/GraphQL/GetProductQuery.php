<?php

declare(strict_types=1);

namespace App\CatalogSync\Infrastructure\Shopify\GraphQL;

final readonly class GetProductQuery
{
    public const QUERY = <<<'GRAPHQL'
        query GetProduct($id: ID!) {
          product(id: $id) {
            id
            title
            handle
            vendor
            productType
            status
            featuredImage { url }
            images(first: 10) {
              edges {
                node { url altText width height }
              }
            }
          }
        }
        GRAPHQL;

    public static function variables(string $gid): array
    {
        return ['id' => $gid];
    }
}
