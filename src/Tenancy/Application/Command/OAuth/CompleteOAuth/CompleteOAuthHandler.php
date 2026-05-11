<?php

declare(strict_types=1);

namespace App\Tenancy\Application\Command\OAuth\CompleteOAuth;

use App\Tenancy\Domain\Exception\InvalidOAuthCallbackException;
use App\Tenancy\Domain\Model\Tenant;
use App\Tenancy\Domain\Repository\TenantRepositoryInterface;
use App\Tenancy\Domain\Service\OAuth\OAuthStateStoreInterface;
use App\Tenancy\Domain\Service\OAuth\ShopifyHmacValidatorInterface;
use App\Tenancy\Domain\Service\OAuth\ShopifyOAuthClientInterface;
use App\Tenancy\Domain\ValueObject\TenantStatus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CompleteOAuthHandler
{
    public function __construct(
        private readonly ShopifyHmacValidatorInterface $hmacValidator,
        private readonly OAuthStateStoreInterface $stateStore,
        private readonly ShopifyOAuthClientInterface $oauthClient,
        private readonly TenantRepositoryInterface $tenantRepository,
        #[Autowire('%env(SHOPIFY_WEBHOOK_SECRET)%')] private readonly string $webhookSecret,
    ) {}

    public function __invoke(CompleteOAuthCommand $command): void
    {
        if (!$this->hmacValidator->validate($command->hmac, $command->queryParams)) {
            throw InvalidOAuthCallbackException::hmacMismatch();
        }

        $storedShopDomain = $this->stateStore->consume($command->state);
        if ($storedShopDomain !== $command->shopDomain) {
            throw InvalidOAuthCallbackException::shopDomainMismatch();
        }

        $tokenResult = $this->oauthClient->exchangeCodeForToken($command->shopDomain, $command->code);

        $now = new \DateTimeImmutable();
        $tenant = $this->tenantRepository->findByShopDomain($command->shopDomain);

        if ($tenant === null) {
            $shopHandle = (string) strstr($command->shopDomain, '.', before_needle: true);
            $tenant = Tenant::create($shopHandle, $shopHandle, $command->shopDomain, $now);
        } elseif ($tenant->status() === TenantStatus::Uninstalled) {
            $tenant->reinstall($now);
        }

        $tenant->storeCredentials($tokenResult->accessToken, $this->webhookSecret, $tokenResult->scope);
        $this->tenantRepository->save($tenant);
    }
}
