<?php

namespace Database\Factories;

use App\Models\Rider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rider>
 */
class RiderFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+92300'.fake()->unique()->numerify('#######'),
            'password' => 'password',
        ];
    }
}
