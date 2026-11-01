<?php

namespace App\Services;

use App\Enums\DriverAvailability;
use App\Enums\RideCategory;
use App\Enums\RideStatus;
use App\Models\Driver;
use App\Models\Ride;
use App\Support\GeoPoint;
use Illuminate\Support\Collection;

/**
 * Simplified nearby-driver discovery. A lat/lng bounding box narrows candidates
 * in SQL, then exact Haversine distance ranks them in PHP. Production would use
 * a geospatial index (Redis GEO / H3 cells / PostGIS) fed by live driver pings.
 *
 * Read-only: nothing here reserves a driver. Assignment safety is enforced by the
 * row locks in RideService::accept().
 */
class DriverMatchingService
{
    /**
     * Available drivers of the category within the matching radius, nearest first.
     *
     * @return Collection<int, array{driver: Driver, distance_km: float}>
     */
    public function nearbyAvailableDrivers(GeoPoint $pickup, RideCategory $category): Collection
    {
        $radius = (float) config('rides.matching.radius_km');
        $box = $pickup->boundingBox($radius);

        return Driver::query()
            ->where('availability_status', DriverAvailability::Available)
            ->where('vehicle_category', $category)
            ->whereBetween('current_latitude', [$box['min_lat'], $box['max_lat']])
            ->whereBetween('current_longitude', [$box['min_lng'], $box['max_lng']])
            ->get()
            ->map(fn (Driver $driver) => [
                'driver' => $driver,
                'distance_km' => $pickup->distanceTo(new GeoPoint($driver->current_latitude, $driver->current_longitude)),
            ])
            ->filter(fn (array $candidate) => $candidate['distance_km'] <= $radius)
            ->sortBy('distance_km')
            ->values();
    }

    /** Pickup ETA from the nearest available driver, or null when nobody is nearby. */
    public function estimatePickupMinutes(GeoPoint $pickup, RideCategory $category): ?int
    {
        $nearest = $this->nearbyAvailableDrivers($pickup, $category)->first();

        return $nearest === null ? null : $this->minutesForDistance($nearest['distance_km']);
    }

    public function minutesForDistance(float $distanceKm): int
    {
        $speed = (float) config('rides.matching.average_city_speed_kmh');

        return (int) ceil($distanceKm / $speed * 60) + (int) config('rides.matching.pickup_buffer_minutes');
    }

    /**
     * Open ride requests a driver is eligible to accept: same category, pickup
     * within the matching radius, oldest-nearest first.
     *
     * @return Collection<int, array{ride: Ride, distance_km: float}>
     */
    public function openRidesFor(Driver $driver): Collection
    {
        if (! $driver->isAvailable() || ! $driver->hasLocation()) {
            return collect();
        }

        $position = new GeoPoint($driver->current_latitude, $driver->current_longitude);
        $radius = (float) config('rides.matching.radius_km');
        $box = $position->boundingBox($radius);

        return Ride::query()
            ->where('status', RideStatus::Requested)
            ->where('category', $driver->vehicle_category)
            ->whereBetween('pickup_latitude', [$box['min_lat'], $box['max_lat']])
            ->whereBetween('pickup_longitude', [$box['min_lng'], $box['max_lng']])
            ->with('rider')
            ->orderBy('requested_at')
            ->limit(50)
            ->get()
            ->map(fn (Ride $ride) => [
                'ride' => $ride,
                'distance_km' => $position->distanceTo(new GeoPoint($ride->pickup_latitude, $ride->pickup_longitude)),
            ])
            ->filter(fn (array $item) => $item['distance_km'] <= $radius)
            ->sortBy('distance_km')
            ->values();
    }
}
