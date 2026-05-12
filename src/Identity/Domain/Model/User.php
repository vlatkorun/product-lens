<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use App\Identity\Domain\Event\UserCreated;
use App\Identity\Domain\ValueObject\UserRole;
use App\Identity\Domain\ValueObject\UserStatus;
use App\Identity\Infrastructure\Persistence\DoctrineUserRepository;
use App\Shared\Domain\Model\AggregateRoot;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity(repositoryClass: DoctrineUserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'users_resource_id_uq', fields: ['resourceId'])]
#[ORM\UniqueConstraint(name: 'users_email_uq', fields: ['email'])]
#[ORM\Index(name: 'users_role_idx', fields: ['role'])]
#[ORM\Index(name: 'users_status_idx', fields: ['status'])]
#[ORM\HasLifecycleCallbacks]
class User extends AggregateRoot implements PasswordAuthenticatedUserInterface, UserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private ?string $id = null;

    #[ORM\Column(name: 'resource_id', type: 'uuid')]
    private UuidV7 $resourceId;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $password;

    #[ORM\Column(type: 'user_role')]
    private UserRole $role;

    #[ORM\Column(type: 'user_status', options: ['default' => 'active'])]
    private UserStatus $status;

    /** @var Collection<int, UserTenantAccess> */
    #[ORM\OneToMany(targetEntity: UserTenantAccess::class, mappedBy: 'user', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $tenantAccesses;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct()
    {
        $this->tenantAccesses = new ArrayCollection();
    }

    public static function create(
        string $email,
        string $hashedPassword,
        UserRole $role,
        \DateTimeImmutable $now,
    ): self {
        if ($role->isTenantScoped()) {
            throw new \InvalidArgumentException(
                \sprintf('Role "%s" requires a tenant. Use User::createForTenant() instead.', $role->value),
            );
        }

        $user = new self();
        $user->resourceId = new UuidV7();
        $user->email = $email;
        $user->password = $hashedPassword;
        $user->role = $role;
        $user->status = UserStatus::Active;
        $user->createdAt = $now;
        $user->updatedAt = $now;

        $user->raise(new UserCreated($user->resourceId->toRfc4122(), $email, $role->value, null, $now));

        return $user;
    }

    public static function createForTenant(
        string $email,
        string $hashedPassword,
        UserRole $role,
        UuidV7 $tenantId,
        \DateTimeImmutable $now,
    ): self {
        if ($role->isGlobal()) {
            throw new \InvalidArgumentException(
                \sprintf('Role "%s" is not tenant-scoped. Use User::create() instead.', $role->value),
            );
        }

        $user = new self();
        $user->resourceId = new UuidV7();
        $user->email = $email;
        $user->password = $hashedPassword;
        $user->role = $role;
        $user->status = UserStatus::Active;
        $user->createdAt = $now;
        $user->updatedAt = $now;
        $user->grantTenantAccess($tenantId);

        $user->raise(new UserCreated($user->resourceId->toRfc4122(), $email, $role->value, $tenantId->toRfc4122(), $now));

        return $user;
    }

    // --- Lifecycle callbacks ---

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // --- Tenant access ---

    public function grantTenantAccess(UuidV7 $tenantId): void
    {
        if ($this->hasTenantAccess($tenantId)) {
            return;
        }

        $this->tenantAccesses->add(new UserTenantAccess($this, $tenantId));
    }

    public function revokeTenantAccess(UuidV7 $tenantId): void
    {
        $this->tenantAccesses = $this->tenantAccesses->filter(
            static fn (UserTenantAccess $a) => !$a->tenantId()->equals($tenantId),
        );
    }

    public function hasTenantAccess(UuidV7 $tenantId): bool
    {
        return $this->tenantAccesses->exists(
            static fn (int $_, UserTenantAccess $a) => $a->tenantId()->equals($tenantId),
        );
    }

    // --- Status transitions ---

    public function activate(): void
    {
        $this->status = UserStatus::Active;
    }

    public function deactivate(): void
    {
        $this->status = UserStatus::Inactive;
    }

    public function changePassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
    }

    // --- UserInterface ---

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return match ($this->role) {
            UserRole::SuperAdmin  => ['ROLE_SUPER_ADMIN'],
            UserRole::Admin       => ['ROLE_ADMIN'],
            UserRole::TenantAdmin => ['ROLE_TENANT_ADMIN'],
            UserRole::Tenant      => ['ROLE_TENANT'],
        };
    }

    public function eraseCredentials(): void
    {
    }

    // --- PasswordAuthenticatedUserInterface ---

    public function getPassword(): string
    {
        return $this->password;
    }

    // --- Accessors ---

    public function id(): UuidV7
    {
        return $this->resourceId;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function role(): UserRole
    {
        return $this->role;
    }

    public function status(): UserStatus
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return UuidV7[] */
    public function tenantIds(): array
    {
        return $this->tenantAccesses->map(static fn (UserTenantAccess $a) => $a->tenantId())->toArray();
    }
}
