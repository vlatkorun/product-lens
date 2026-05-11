<?php

declare(strict_types=1);

namespace App\CatalogSync\Domain\ValueObject;

enum ProductStatus: string
{
    case Active   = 'active';
    case Archived = 'archived';
    case Draft    = 'draft';
}
