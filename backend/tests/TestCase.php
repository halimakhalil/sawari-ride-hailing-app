<?php

namespace Tests;

use App\Models\Driver;
use App\Models\Rider;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /** Liberty Market → Johar Town, a ~10 km trip inside Lahore. */
    protected function rideBody(array $overrides = []): array
    {
        return array_replace_recursive([
            'pickup' => ['address' => 'Liberty Market, Gulberg', 'latitude' => 31.5104, 'longitude' => 74.3446],
            'destination' => ['address' => 'Johar Town', 'latitude' => 31.4697, 'longitude' => 74.2728],
            'category' => 'economy',
        ], $overrides);
    }

    protected function actingAsRider(?Rider $rider = null): Rider
    {
        $rider ??= Rider::factory()->create();
        Sanctum::actingAs($rider, ['rider']);

        return $rider;
    }

    protected function actingAsDriver(?Driver $driver = null): Driver
    {
        $driver ??= Driver::factory()->create();
        Sanctum::actingAs($driver, ['driver']);

        return $driver;
    }
}
