<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity]
#[ORM\Table(name: 'users_tenants')]
class UserTenantAccess
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'tenantAccesses')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'resource_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Id]
    #[ORM\Column(name: 'tenant_id', type: 'uuid')]
    private UuidV7 $tenantId;

    public function __construct(User $user, UuidV7 $tenantId)
    {
        $this->user = $user;
        $this->tenantId = $tenantId;
    }

    public function tenantId(): UuidV7
    {
        return $this->tenantId;
    }
}
