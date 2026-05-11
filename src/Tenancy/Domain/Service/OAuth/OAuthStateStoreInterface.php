<?php

declare(strict_types=1);

namespace App\Tenancy\Domain\Service\OAuth;

use App\Tenancy\Domain\Exception\InvalidOAuthCallbackException;

interface OAuthStateStoreInterface
{
    public function store(string $state, string $shopDomain): void;

    /**
     * Returns the shopDomain associated with the state, then deletes it.
     *
     * @throws InvalidOAuthCallbackException if the state is unknown or expired
     */
    public function consume(string $state): string;
}
