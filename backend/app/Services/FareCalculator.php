<?php

namespace App\Services;

use App\Enums\RideCategory;
use App\Support\GeoPoint;

/**
 * Deterministic fare estimate:
 *
 *   road_km = haversine_km × road_distance_factor
 *   fare    = max(base + per_km × road_km, minimum) × category_multiplier
 *
 * rounded up to the next PKR 5. Production pricing would use routed distance,
 * expected duration, traffic, demand (surge) and promotions.
 */
class FareCalculator
{
    private const ROUND_TO = 5;

    public function quote(GeoPoint $pickup, GeoPoint $destination, RideCategory $category): FareQuote
    {
        $config = config('rides.fare');

        $distanceKm = round($pickup->distanceTo($destination) * $config['road_distance_factor'], 2);
        $multiplier = $category->fareMultiplier();
        $raw = max($config['base'] + $config['per_km'] * $distanceKm, $config['minimum']) * $multiplier;
        // Round before ceil so float noise such as 260.00000001 does not add PKR 5.
        $amount = ceil(round($raw, 2) / self::ROUND_TO) * self::ROUND_TO;

        return new FareQuote($category, $distanceKm, $multiplier, (float) $amount, config('rides.currency'));
    }
}
