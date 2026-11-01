<?php

namespace App\Policies;

use App\Enums\RideStatus;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\Rider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Read access only. State-changing actions re-check identity inside the locked
 * transaction in RideService, where the check cannot race with other writers.
 */
class RidePolicy
{
    public function view(Authenticatable $actor, Ride $ride): bool
    {
        if ($actor instanceof Rider) {
            return $ride->rider_id === $actor->id;
        }

        if ($actor instanceof Driver) {
            // Drivers see open requests they could accept, plus rides they have served.
            return ($ride->status === RideStatus::Requested && $ride->category === $actor->vehicle_category)
                || $ride->assignments()->where('driver_id', $actor->id)->exists();
        }

        return false;
    }
}
