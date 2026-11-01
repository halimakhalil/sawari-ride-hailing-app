<?php

namespace Database\Factories;

use App\Enums\DriverAvailability;
use App\Enums\RideCategory;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+92321'.fake()->unique()->numerify('#######'),
            'password' => 'password',
            'vehicle_category' => RideCategory::Economy,
            'vehicle_model' => 'Suzuki Alto',
            'vehicle_plate' => 'LE'.fake()->unique()->bothify('?-####'),
            'availability_status' => DriverAvailability::Available,
            // Gulberg, Lahore
            'current_latitude' => 31.5150,
            'current_longitude' => 74.3480,
            'location_updated_at' => now(),
        ];
    }

    public function comfort(): static
    {
        return $this->state(['vehicle_category' => RideCategory::Comfort, 'vehicle_model' => 'Toyota Corolla']);
    }

    public function offline(): static
    {
        return $this->state(['availability_status' => DriverAvailability::Offline]);
    }

    public function at(float $latitude, float $longitude): static
    {
        return $this->state(['current_latitude' => $latitude, 'current_longitude' => $longitude]);
    }
}
