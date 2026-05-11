<?php

declare(strict_types=1);

namespace App\Tenancy\Domain\ValueObject;

enum FeatureFlag: string
{
    case ImageAudit   = 'image_audit';
    case AiImageAudit = 'ai_image_audit';
}
