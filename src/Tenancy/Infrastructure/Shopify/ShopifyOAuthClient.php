<?php

declare(strict_types=1);

namespace App\Tenancy\Infrastructure\Shopify;

use App\Tenancy\Domain\Service\OAuth\ShopifyOAuthClientInterface;
use App\Tenancy\Domain\ValueObject\ShopifyTokenResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ShopifyOAuthClient implements ShopifyOAuthClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(SHOPIFY_API_KEY)%')] private string $apiKey,
        #[Autowire('%env(SHOPIFY_API_SECRET)%')] private string $apiSecret,
    ) {}

    public function exchangeCodeForToken(string $shopDomain, string $code): ShopifyTokenResult
    {
        $response = $this->httpClient->request(
            'POST',
            sprintf('https://%s/admin/oauth/access_token', $shopDomain),
            [
                'json' => [
                    'client_id'     => $this->apiKey,
                    'client_secret' => $this->apiSecret,
                    'code'          => $code,
                ],
            ],
        );

        $data = $response->toArray();

        return new ShopifyTokenResult(
            accessToken: $data['access_token'],
            scope: $data['scope'],
        );
    }
}
