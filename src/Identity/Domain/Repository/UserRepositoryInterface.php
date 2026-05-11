<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Model\User;
use Symfony\Component\Uid\UuidV7;

interface UserRepositoryInterface
{
    public function findById(UuidV7 $id): ?User;

    public function findByEmail(string $email): ?User;

    public function save(User $user): void;
}
