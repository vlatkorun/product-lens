<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Api;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ShopifyClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiVersion,
    ) {
    }

    /**
     * @param array<string, mixed> $variables
     *
     * @return array<string, mixed>
     */
    public function query(string $shopDomain, string $accessToken, string $query, array $variables): array
    {
        $response = $this->httpClient->request(
            'POST',
            \sprintf('https://%s/admin/api/%s/graphql.json', $shopDomain, $this->apiVersion),
            [
                'headers' => [
                    'X-Shopify-Access-Token' => $accessToken,
                    'Content-Type'           => 'application/json',
                ],
                'json' => ['query' => $query, 'variables' => $variables],
            ],
        );

        $data = $response->toArray();

        if (!empty($data['errors'])) {
            throw new \RuntimeException(\sprintf('Shopify GraphQL error: %s', \json_encode($data['errors'])));
        }

        return $data;
    }
}
