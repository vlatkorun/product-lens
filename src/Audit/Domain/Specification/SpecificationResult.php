<?php

declare(strict_types=1);

namespace App\Audit\Domain\Specification;

use App\Audit\Domain\Specification\ValueObject\Severity;

final readonly class SpecificationResult
{
    private function __construct(
        private string $specificationName,
        private bool $passed,
        private ?Issue $issue,
        private ?Severity $severity,
    ) {
    }

    public static function pass(string $specificationName): self
    {
        return new self($specificationName, true, null, null);
    }

    public static function fail(string $specificationName, Issue $issue, Severity $severity): self
    {
        return new self($specificationName, false, $issue, $severity);
    }

    public function specificationName(): string
    {
        return $this->specificationName;
    }

    public function passed(): bool
    {
        return $this->passed;
    }

    public function failed(): bool
    {
        return !$this->passed;
    }

    public function issue(): ?Issue
    {
        return $this->issue;
    }

    public function severity(): ?Severity
    {
        return $this->severity;
    }
}
