<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\FetchNextPage;

final readonly class FetchNextPageCommand
{
    public function __construct(public string $syncJobId)
    {
    }
}
