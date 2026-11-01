<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A ride request in a driver's feed, with the distance from the driver to the pickup.
 *
 * @property array{ride: \App\Models\Ride, distance_km: float} $resource
 */
class OpenRideResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...(new RideResource($this->resource['ride']))->toArray($request),
            'pickup_distance_km' => round($this->resource['distance_km'], 2),
        ];
    }
}
