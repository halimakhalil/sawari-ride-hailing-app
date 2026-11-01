<?php

namespace App\Http\Resources;

use App\Enums\AssignmentStatus;
use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ride
 */
class RideResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'category' => $this->category->value,
            'pickup' => [
                'address' => $this->pickup_address,
                'latitude' => $this->pickup_latitude,
                'longitude' => $this->pickup_longitude,
            ],
            'destination' => [
                'address' => $this->destination_address,
                'latitude' => $this->destination_latitude,
                'longitude' => $this->destination_longitude,
            ],
            'distance_km' => (float) $this->distance_km,
            // Money is serialised as a decimal string to avoid floating point drift.
            'fare' => [
                'currency' => $this->currency,
                'estimated' => $this->estimated_fare,
                'final' => $this->final_fare,
            ],
            'estimated_pickup_minutes' => $this->estimated_pickup_minutes,
            'can_cancel' => $this->status->isCancellableByRider(),
            'rider' => $this->whenLoaded('rider', fn () => [
                'id' => $this->rider->id,
                'name' => $this->rider->name,
            ]),
            'driver' => $this->whenLoaded('currentAssignment', fn () => $this->assignedDriver()),
            'cancellation_reason' => $this->cancellation_reason,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function assignedDriver(): ?array
    {
        $assignment = $this->currentAssignment;

        if ($assignment === null || $assignment->status === AssignmentStatus::Cancelled) {
            return null;
        }

        return (new DriverResource($assignment->driver))->publicProfile();
    }
}
