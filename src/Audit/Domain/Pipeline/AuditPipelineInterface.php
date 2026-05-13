<?php

declare(strict_types=1);

namespace App\Audit\Domain\Pipeline;

use App\Audit\Domain\ValueObject\AuditableObject;
use App\Shared\Domain\ValueObject\FeatureFlag;

interface AuditPipelineInterface
{
    public function name(): string;

    public function requiredFeatureFlag(): ?FeatureFlag;

    public function run(AuditableObject $subject): AuditPipelineResult;
}
