<?php

declare(strict_types=1);

namespace App\Audit\Domain\Specification;

final readonly class Issue
{
    private function __construct(
        private string $code,
        private string $message,
    ) {
    }

    public static function of(string $code, string $message): self
    {
        return new self($code, $message);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }
}
