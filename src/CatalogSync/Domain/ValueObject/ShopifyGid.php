<?php

declare(strict_types=1);

namespace App\CatalogSync\Domain\ValueObject;

final readonly class ShopifyGid
{
    private function __construct(public string $value)
    {
    }

    public static function fromString(string $gid): self
    {
        if (!\preg_match('#^gid://shopify/[A-Za-z]+/\d+$#', $gid)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Shopify GID: %s', $gid));
        }

        return new self($gid);
    }

    public static function product(int|string $id): self
    {
        return new self(\sprintf('gid://shopify/Product/%s', $id));
    }

    public static function collection(int|string $id): self
    {
        return new self(\sprintf('gid://shopify/Collection/%s', $id));
    }

    public function type(): string
    {
        \preg_match('#^gid://shopify/([A-Za-z]+)/\d+$#', $this->value, $matches);

        return $matches[1];
    }

    public function numericId(): string
    {
        \preg_match('#^gid://shopify/[A-Za-z]+/(\d+)$#', $this->value, $matches);

        return $matches[1];
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
