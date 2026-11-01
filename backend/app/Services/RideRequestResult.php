<?php

namespace App\Services;

use App\Models\Ride;

final readonly class RideRequestResult
{
    public function __construct(
        public Ride $ride,
        /** True when an idempotent retry returned the ride created by an earlier request. */
        public bool $replayed,
    ) {}
}
