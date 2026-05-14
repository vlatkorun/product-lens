<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

enum AuditCheck: string
{
    case ImageAudit = 'image_audit';
    case AiImageAudit = 'ai_image_audit';
}
