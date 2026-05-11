<?php

declare(strict_types=1);

namespace App\Tenancy\Application\Command\OAuth\BeginOAuth;

use App\Tenancy\Domain\Service\OAuth\OAuthStateStoreInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class BeginOAuthHandler
{
    public function __construct(
        private readonly OAuthStateStoreInterface $stateStore,
        #[Autowire('%env(SHOPIFY_API_KEY)%')] private readonly string $apiKey,
        #[Autowire('%env(SHOPIFY_OAUTH_SCOPES)%')] private readonly string $scopes,
        #[Autowire('%env(SHOPIFY_OAUTH_REDIRECT_URI)%')] private readonly string $redirectUri,
    ) {}

    public function __invoke(BeginOAuthCommand $command): string
    {
        if (!str_ends_with($command->shopDomain, '.myshopify.com')) {
            throw new \InvalidArgumentException(
                sprintf('Invalid shop domain: %s', $command->shopDomain),
            );
        }

        $state = bin2hex(random_bytes(16));
        $this->stateStore->store($state, $command->shopDomain);

        return sprintf(
            'https://%s/admin/oauth/authorize?%s',
            $command->shopDomain,
            http_build_query([
                'client_id'    => $this->apiKey,
                'scope'        => $this->scopes,
                'redirect_uri' => $this->redirectUri,
                'state'        => $state,
            ]),
        );
    }
}
