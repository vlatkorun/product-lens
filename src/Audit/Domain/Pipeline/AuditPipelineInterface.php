<?php

declare(strict_types=1);

namespace App\Audit\Domain\Pipeline;

use App\Audit\Domain\ValueObject\AuditableObject;
use App\Shared\Domain\ValueObject\AuditCheck;

interface AuditPipelineInterface
{
    public function name(): string;

    public function requiredFeatureFlag(): ?AuditCheck;

    public function run(AuditableObject $subject): AuditPipelineResult;
}
