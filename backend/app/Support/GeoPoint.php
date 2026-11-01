<?php

namespace App\Support;

final readonly class GeoPoint
{
    private const EARTH_RADIUS_KM = 6371.0;

    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {}

    /** Great-circle (Haversine) distance in kilometres. */
    public function distanceTo(GeoPoint $other): float
    {
        $lat1 = deg2rad($this->latitude);
        $lat2 = deg2rad($other->latitude);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad($other->longitude - $this->longitude);

        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a)));
    }

    /**
     * Lat/lng bounding box enclosing a circle of the given radius. Used as a cheap
     * SQL pre-filter before the exact Haversine check.
     *
     * @return array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}
     */
    public function boundingBox(float $radiusKm): array
    {
        $latDelta = rad2deg($radiusKm / self::EARTH_RADIUS_KM);
        $lngDelta = rad2deg($radiusKm / (self::EARTH_RADIUS_KM * cos(deg2rad($this->latitude))));

        return [
            'min_lat' => $this->latitude - $latDelta,
            'max_lat' => $this->latitude + $latDelta,
            'min_lng' => $this->longitude - $lngDelta,
            'max_lng' => $this->longitude + $lngDelta,
        ];
    }
}
