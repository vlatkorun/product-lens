<?php

declare(strict_types=1);

namespace App\Audit\Domain\Pipeline;

use App\Audit\Domain\Specification\SpecificationResult;

final readonly class AuditPipelineResult
{
    /** @param list<SpecificationResult> $results */
    public function __construct(
        private string $pipelineName,
        private array $results,
    ) {
    }

    public function pipelineName(): string
    {
        return $this->pipelineName;
    }

    /** @return list<SpecificationResult> */
    public function results(): array
    {
        return $this->results;
    }

    public function passed(): bool
    {
        foreach ($this->results as $result) {
            if ($result->failed()) {
                return false;
            }
        }

        return true;
    }

    /** @return list<SpecificationResult> */
    public function failures(): array
    {
        $failures = [];
        foreach ($this->results as $result) {
            if ($result->failed()) {
                $failures[] = $result;
            }
        }

        return $failures;
    }
}
