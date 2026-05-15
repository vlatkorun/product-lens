<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Api\GraphQL\Product\Query;

final readonly class ProductsByCollectionQuery
{
    public const QUERY = <<<'GRAPHQL'
        query ProductsByCollection($collectionId: ID!, $first: Int!, $after: String) {
          collection(id: $collectionId) {
            products(first: $first, after: $after) {
              pageInfo { hasNextPage endCursor }
              edges {
                node {
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
                  variants(first: 50) {
                    edges {
                      node {
                        id
                        title
                        image { url altText width height }
                      }
                    }
                  }
                }
              }
            }
          }
        }
        GRAPHQL;

    public static function variables(string $collectionGid, int $first, ?string $after): array
    {
        return [
            'collectionId' => $collectionGid,
            'first'        => $first,
            'after'        => $after,
        ];
    }
}
