<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Api;

use App\Catalog\Infrastructure\Api\Exception\ShopifyThrottledException;
use App\Catalog\Infrastructure\Api\GraphQL\Product\Query\Dto\ApiCostDto;
use App\Shared\Domain\RateLimit\RequestCost;
use App\Shared\Domain\RateLimit\ThrottleStatus;
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
     */
    public function query(string $shopDomain, string $accessToken, string $query, array $variables): ShopifyApiResponse
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
            foreach ($data['errors'] as $error) {
                if (($error['extensions']['code'] ?? null) === 'THROTTLED') {
                    throw new ShopifyThrottledException($shopDomain);
                }
            }

            throw new \RuntimeException(\sprintf('Shopify GraphQL error: %s', \json_encode($data['errors'])));
        }

        if (isset($data['extensions']['cost'])) {
            $cost = ApiCostDto::fromExtensionsCost($data['extensions']['cost']);
            $requestCost = $cost->toRequestCost();
            $throttleStatus = $cost->throttleStatus;
        } else {
            $requestCost = new RequestCost(0, 0);
            $throttleStatus = new ThrottleStatus(2000, 2000, 100);
        }

        return new ShopifyApiResponse($data, $requestCost, $throttleStatus);
    }
}
