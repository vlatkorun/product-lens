<?php

declare(strict_types=1);

namespace App\Tenancy\Domain\Model;

use App\Shared\Domain\Model\AggregateRoot;
use App\Tenancy\Domain\Event\TenantCreated;
use App\Tenancy\Domain\Repository\TenantRepositoryInterface;
use App\Tenancy\Domain\ValueObject\FeatureFlag;
use App\Tenancy\Domain\ValueObject\TenantStatus;
use App\Tenancy\Infrastructure\Persistence\DoctrineTenantRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity(repositoryClass: DoctrineTenantRepository::class)]
#[ORM\Table(name: 'tenants')]
#[ORM\UniqueConstraint(name: 'tenants_shop_domain_uq', fields: ['shopDomain'])]
#[ORM\UniqueConstraint(name: 'tenants_shop_handle_uq', fields: ['shopHandle'])]
#[ORM\Index(name: 'tenants_status_idx', fields: ['status'])]
#[ORM\HasLifecycleCallbacks]
class Tenant extends AggregateRoot
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private UuidV7 $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(name: 'shop_handle', length: 255)]
    private string $shopHandle;

    #[ORM\Column(name: 'shop_domain', length: 255)]
    private string $shopDomain;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'currency_code', length: 3, nullable: true)]
    private ?string $currencyCode = null;

    #[ORM\Column(name: 'country_code', length: 2, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $timezone = null;

    #[ORM\Column(name: 'shopify_plan', length: 100, nullable: true)]
    private ?string $shopifyPlan = null;

    #[ORM\Column(name: 'shopify_scope', type: 'text', nullable: true)]
    private ?string $shopifyScope = null;

    #[ORM\Column(columnDefinition: "tenant_status NOT NULL DEFAULT 'active'", enumType: TenantStatus::class)]
    private TenantStatus $status;

    #[ORM\Column(name: 'installed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $installedAt = null;

    #[ORM\Column(name: 'uninstalled_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $uninstalledAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'shopify_access_token', type: 'encrypted_string', nullable: true)]
    private ?string $shopifyAccessToken = null;

    #[ORM\Column(name: 'shopify_webhook_secret', type: 'encrypted_string', nullable: true)]
    private ?string $shopifyWebhookSecret = null;

    #[ORM\Column(type: 'json', columnDefinition: "JSONB NOT NULL DEFAULT '{}'")]
    private array $configuration = [];

    /** @var string[] raw backing values for $featureFlags, persisted to DB */
    #[ORM\Column(name: 'feature_flags', type: 'json', columnDefinition: "JSONB NOT NULL DEFAULT '[]'")]
    private array $featureFlagsRaw = [];

    /** @var FeatureFlag[] transient — hydrated from $featureFlagsRaw on PostLoad */
    private array $featureFlags = [];

    private function __construct() {}

    public static function create(
        string $name,
        string $shopHandle,
        string $shopDomain,
        \DateTimeImmutable $now,
    ): self {
        if (!str_ends_with($shopDomain, '.myshopify.com')) {
            throw new \InvalidArgumentException(
                sprintf('shopDomain must end with .myshopify.com, got: %s', $shopDomain),
            );
        }

        $tenant = new self();
        $tenant->id = new UuidV7();
        $tenant->name = $name;
        $tenant->shopHandle = $shopHandle;
        $tenant->shopDomain = $shopDomain;
        $tenant->status = TenantStatus::Active;
        $tenant->installedAt = $now;
        $tenant->createdAt = $now;
        $tenant->updatedAt = $now;

        $tenant->raise(new TenantCreated($tenant->id->toRfc4122(), $shopDomain, $now));

        return $tenant;
    }

    // --- Lifecycle callbacks ---

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->serializeFeatureFlags();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->serializeFeatureFlags();
    }

    #[ORM\PostLoad]
    public function onPostLoad(): void
    {
        $this->hydrateFeatureFlags();
    }

    private function serializeFeatureFlags(): void
    {
        $this->featureFlagsRaw = array_map(
            static fn(FeatureFlag $flag): string => $flag->value,
            $this->featureFlags,
        );
    }

    private function hydrateFeatureFlags(): void
    {
        $this->featureFlags = array_map(
            static fn(string $value): FeatureFlag => FeatureFlag::from($value),
            $this->featureFlagsRaw ?? [],
        );
    }

    // --- Feature flag management ---

    public function enableFeature(FeatureFlag $flag): void
    {
        if ($this->hasFeature($flag)) {
            return;
        }

        $this->featureFlags[] = $flag;
    }

    public function disableFeature(FeatureFlag $flag): void
    {
        $this->featureFlags = array_values(
            array_filter(
                $this->featureFlags,
                static fn(FeatureFlag $f): bool => $f !== $flag,
            ),
        );
    }

    public function hasFeature(FeatureFlag $flag): bool
    {
        return in_array($flag, $this->featureFlags, strict: true);
    }

    // --- Shopify OAuth credentials ---

    public function storeCredentials(string $accessToken, string $webhookSecret, string $scope): void
    {
        $this->shopifyAccessToken = $accessToken;
        $this->shopifyWebhookSecret = $webhookSecret;
        $this->shopifyScope = $scope;
    }

    public function shopifyAccessToken(): ?string
    {
        return $this->shopifyAccessToken;
    }

    public function shopifyWebhookSecret(): ?string
    {
        return $this->shopifyWebhookSecret;
    }

    // --- Shopify Shop metadata (populated after OAuth) ---

    public function updateShopMetadata(
        ?string $email,
        ?string $currencyCode,
        ?string $countryCode,
        ?string $timezone,
        ?string $shopifyPlan,
    ): void {
        $this->email = $email;
        $this->currencyCode = $currencyCode;
        $this->countryCode = $countryCode;
        $this->timezone = $timezone;
        $this->shopifyPlan = $shopifyPlan;
    }

    // --- Lifecycle transitions ---

    public function markUninstalled(\DateTimeImmutable $at): void
    {
        $this->status = TenantStatus::Uninstalled;
        $this->uninstalledAt = $at;
    }

    public function suspend(): void
    {
        $this->status = TenantStatus::Suspended;
    }

    public function reactivate(): void
    {
        $this->status = TenantStatus::Active;
        $this->uninstalledAt = null;
    }

    // --- Accessors ---

    public function id(): UuidV7
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function shopHandle(): string
    {
        return $this->shopHandle;
    }

    public function shopDomain(): string
    {
        return $this->shopDomain;
    }

    public function status(): TenantStatus
    {
        return $this->status;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function currencyCode(): ?string
    {
        return $this->currencyCode;
    }

    public function countryCode(): ?string
    {
        return $this->countryCode;
    }

    public function timezone(): ?string
    {
        return $this->timezone;
    }

    public function shopifyPlan(): ?string
    {
        return $this->shopifyPlan;
    }

    public function shopifyScope(): ?string
    {
        return $this->shopifyScope;
    }

    public function installedAt(): ?\DateTimeImmutable
    {
        return $this->installedAt;
    }

    public function uninstalledAt(): ?\DateTimeImmutable
    {
        return $this->uninstalledAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function configuration(): array
    {
        return $this->configuration;
    }

    /** @return FeatureFlag[] */
    public function featureFlags(): array
    {
        return $this->featureFlags;
    }
}
