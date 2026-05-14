<?php

declare(strict_types=1);

namespace App\Tenancy\Domain\Model;

use App\Shared\Domain\Model\AggregateRoot;
use App\Shared\Domain\ValueObject\AuditCheck;
use App\Tenancy\Domain\Event\TenantCreated;
use App\Tenancy\Domain\Event\TenantReinstalled;
use App\Tenancy\Domain\Exception\AuditCheckEnabledException;
use App\Tenancy\Domain\ValueObject\TenantStatus;
use App\Tenancy\Infrastructure\Persistence\DoctrineTenantRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity(repositoryClass: DoctrineTenantRepository::class)]
#[ORM\Table(name: 'tenants')]
#[ORM\UniqueConstraint(name: 'tenants_resource_id_uq', fields: ['resourceId'])]
#[ORM\UniqueConstraint(name: 'tenants_shop_domain_uq', fields: ['shopDomain'])]
#[ORM\UniqueConstraint(name: 'tenants_shop_handle_uq', fields: ['shopHandle'])]
#[ORM\Index(name: 'tenants_status_idx', fields: ['status'])]
#[ORM\Index(name: 'tenants_installed_at_idx', fields: ['installedAt'])]
#[ORM\HasLifecycleCallbacks]
class Tenant extends AggregateRoot
{
    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private ?int $id = null;

    #[ORM\Column(name: 'resource_id', type: 'uuid')]
    private UuidV7 $resourceId;

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

    #[ORM\Column(type: 'tenant_status', options: ['default' => 'active'])]
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

    #[ORM\Column(type: 'jsonb', options: ['default' => '{}'])]
    private array $configuration = [];

    /** @var AuditCheck[] transient — hydrated from configuration['audit']['checks'] on PostLoad */
    private array $auditChecks = [];

    private function __construct()
    {
    }

    public static function create(
        string $name,
        string $shopHandle,
        string $shopDomain,
        \DateTimeImmutable $now,
    ): self {
        if (!\str_ends_with($shopDomain, '.myshopify.com')) {
            throw new \InvalidArgumentException(
                \sprintf('shopDomain must end with .myshopify.com, got: %s', $shopDomain),
            );
        }

        $tenant = new self();
        $tenant->resourceId = new UuidV7();
        $tenant->name = $name;
        $tenant->shopHandle = $shopHandle;
        $tenant->shopDomain = $shopDomain;
        $tenant->status = TenantStatus::Active;
        $tenant->installedAt = $now;
        $tenant->createdAt = $now;
        $tenant->updatedAt = $now;

        $tenant->raise(new TenantCreated($tenant->resourceId->toRfc4122(), $shopDomain, $now));

        return $tenant;
    }

    // --- Lifecycle callbacks ---

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->serializeAuditChecks();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->serializeAuditChecks();
    }

    #[ORM\PostLoad]
    public function onPostLoad(): void
    {
        $this->hydrateAuditChecks();
    }

    // --- Audit check management ---

    public function enableAuditCheck(AuditCheck $check): void
    {
        if ($this->hasAuditCheck($check)) {
            throw AuditCheckEnabledException::forFlag($check);
        }

        $this->auditChecks[] = $check;
    }

    public function disableAuditCheck(AuditCheck $check): void
    {
        $this->auditChecks = \array_values(
            \array_filter(
                $this->auditChecks,
                static fn (AuditCheck $c): bool => $c !== $check,
            ),
        );
    }

    public function hasAuditCheck(AuditCheck $check): bool
    {
        return \in_array($check, $this->auditChecks, strict: true);
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

    public function reinstall(\DateTimeImmutable $at): void
    {
        $this->reactivate();
        $this->installedAt = $at;
        $this->raise(new TenantReinstalled($this->resourceId->toRfc4122(), $this->shopDomain, $at));
    }

    // --- Accessors ---

    public function id(): UuidV7
    {
        return $this->resourceId;
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

    /** @return AuditCheck[] */
    public function auditChecks(): array
    {
        return $this->auditChecks;
    }

    private function serializeAuditChecks(): void
    {
        $this->configuration['audit']['checks'] = \array_map(
            static fn (AuditCheck $check): string => $check->value,
            $this->auditChecks,
        );
    }

    private function hydrateAuditChecks(): void
    {
        $this->auditChecks = \array_map(
            static fn (string $value): AuditCheck => AuditCheck::from($value),
            $this->configuration['audit']['checks'] ?? [],
        );
    }
}
