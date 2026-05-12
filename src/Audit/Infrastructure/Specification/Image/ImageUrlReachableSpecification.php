<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Specification\Image;

use App\Audit\Domain\Specification\Issue;
use App\Audit\Domain\Specification\SpecificationInterface;
use App\Audit\Domain\Specification\SpecificationResult;
use App\Audit\Domain\Specification\ValueObject\Severity;
use App\Audit\Domain\ValueObject\AuditableProduct;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AutoconfigureTag('app.audit_specification.image', attributes: ['priority' => 50])]
final class ImageUrlReachableSpecification implements SpecificationInterface
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function isSatisfiedBy(AuditableProduct $product): SpecificationResult
    {
        foreach ($product->images() as $image) {
            try {
                $statusCode = $this->httpClient->request('HEAD', $image->url, ['timeout' => 5])->getStatusCode();
            } catch (\Throwable) {
                $statusCode = 0;
            }

            if ($statusCode < 200 || $statusCode >= 400) {
                return SpecificationResult::fail(
                    self::class,
                    Issue::of('IMAGE_URL_UNREACHABLE', \sprintf('Image URL is not reachable: %s', $image->url)),
                    Severity::WARNING,
                );
            }
        }

        return SpecificationResult::pass(self::class);
    }
}
