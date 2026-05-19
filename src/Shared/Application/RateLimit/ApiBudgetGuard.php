<?php

declare(strict_types=1);

namespace App\Shared\Application\RateLimit;

use App\Shared\Domain\RateLimit\BucketId;
use App\Shared\Domain\RateLimit\CreditsStoreInterface;
use App\Shared\Domain\RateLimit\RequestCost;
use App\Shared\Domain\RateLimit\Reservation;
use App\Shared\Domain\RateLimit\ThrottleStateStoreInterface;
use App\Shared\Domain\RateLimit\ThrottleStatus;
use App\Shared\Domain\RateLimit\TokenBucketLimiterInterface;

final readonly class ApiBudgetGuard
{
    public function __construct(
        private TokenBucketLimiterInterface $tokenBucketLimiter,
        private CreditsStoreInterface $creditsStore,
        private ThrottleStateStoreInterface $throttleStateStore,
        private int $safetyBuffer = 50,
    ) {
    }

    public function reserve(BucketId $bucketId, int $estimatedCost): Reservation
    {
        $status = $this->throttleStateStore->read($bucketId);

        if ($status !== null && $status->currentlyAvailable < ($estimatedCost + $this->safetyBuffer)) {
            $deficit = ($estimatedCost + $this->safetyBuffer) - $status->currentlyAvailable;
            $retryAfterSeconds = (int) \ceil($deficit / \max(1, $status->restoreRate));

            return Reservation::denied($retryAfterSeconds);
        }

        $creditsApplied = $this->creditsStore->consume($bucketId, $estimatedCost);
        $adjustedCost = $estimatedCost - $creditsApplied;

        if ($adjustedCost === 0) {
            return Reservation::granted($estimatedCost);
        }

        $reservation = $this->tokenBucketLimiter->tryConsume($bucketId, $adjustedCost);

        if (!$reservation->granted) {
            $this->creditsStore->add($bucketId, $creditsApplied);

            return $reservation;
        }

        return Reservation::granted($estimatedCost);
    }

    public function reconcile(BucketId $bucketId, RequestCost $cost): void
    {
        $overcharge = $cost->overcharge();

        if ($overcharge > 0) {
            $this->creditsStore->add($bucketId, $overcharge);
        }
    }

    public function syncFromResponse(BucketId $bucketId, ThrottleStatus $throttle): void
    {
        $this->throttleStateStore->write($bucketId, $throttle);
    }
}
