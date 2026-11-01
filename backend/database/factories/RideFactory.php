<?php

namespace Database\Factories;

use App\Enums\RideCategory;
use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\Rider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A requested Liberty Market → Johar Town ride. Use RideService to move it
 * through the lifecycle so assignments and driver state stay consistent.
 *
 * @extends Factory<Ride>
 */
class RideFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'rider_id' => Rider::factory(),
            'category' => RideCategory::Economy,
            'status' => RideStatus::Requested,
            'pickup_address' => 'Liberty Market',
            'pickup_latitude' => 31.5104,
            'pickup_longitude' => 74.3446,
            'destination_address' => 'Johar Town',
            'destination_latitude' => 31.4697,
            'destination_longitude' => 74.2728,
            'distance_km' => 10.20,
            'currency' => 'PKR',
            'fare_multiplier' => 1.00,
            'estimated_fare' => 580.00,
            'estimated_pickup_minutes' => 4,
            'requested_at' => now(),
        ];
    }

    public function comfort(): static
    {
        return $this->state(['category' => RideCategory::Comfort, 'fare_multiplier' => 1.45, 'estimated_fare' => 845.00]);
    }
}
