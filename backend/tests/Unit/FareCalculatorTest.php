<?php

namespace Tests\Unit;

use App\Enums\RideCategory;
use App\Services\FareCalculator;
use App\Support\GeoPoint;
use Tests\TestCase;

class FareCalculatorTest extends TestCase
{
    private FareCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'rides.currency' => 'PKR',
            'rides.fare.base' => 120,
            'rides.fare.per_km' => 45,
            'rides.fare.minimum' => 200,
            'rides.fare.road_distance_factor' => 1.3,
            'rides.fare.multipliers' => ['economy' => 1.0, 'comfort' => 1.45],
        ]);

        $this->calculator = new FareCalculator;
    }

    public function test_haversine_distance_matches_a_known_value(): void
    {
        $liberty = new GeoPoint(31.5104, 74.3446);
        $joharTown = new GeoPoint(31.4697, 74.2728);

        $this->assertEqualsWithDelta(8.18, $liberty->distanceTo($joharTown), 0.01);
        $this->assertSame(0.0, $liberty->distanceTo($liberty));
    }

    public function test_economy_fare_is_base_plus_distance_rate_rounded_up_to_five(): void
    {
        $quote = $this->calculator->quote(new GeoPoint(31.5104, 74.3446), new GeoPoint(31.4697, 74.2728), RideCategory::Economy);

        // 8.18 km × 1.3 = 10.63 road km; 120 + 45 × 10.63 = 598.35 → 600
        $this->assertSame(10.63, $quote->distanceKm);
        $this->assertSame(600.0, $quote->amount);
        $this->assertSame('PKR', $quote->currency);
    }

    public function test_comfort_applies_its_multiplier(): void
    {
        $quote = $this->calculator->quote(new GeoPoint(31.5104, 74.3446), new GeoPoint(31.4697, 74.2728), RideCategory::Comfort);

        // 598.35 × 1.45 = 867.61 → 870
        $this->assertSame(870.0, $quote->amount);
        $this->assertSame(1.45, $quote->multiplier);
    }

    public function test_short_trips_are_charged_the_minimum_fare(): void
    {
        $quote = $this->calculator->quote(new GeoPoint(31.5104, 74.3446), new GeoPoint(31.5130, 74.3460), RideCategory::Economy);

        $this->assertSame(200.0, $quote->amount);
    }

    public function test_quotes_are_deterministic_and_never_negative(): void
    {
        $a = $this->calculator->quote(new GeoPoint(31.47, 74.41), new GeoPoint(31.58, 74.31), RideCategory::Economy);
        $b = $this->calculator->quote(new GeoPoint(31.47, 74.41), new GeoPoint(31.58, 74.31), RideCategory::Economy);

        $this->assertEquals($a, $b);
        $this->assertGreaterThan(0, $a->amount);
        $this->assertSame(0.0, fmod($a->amount, 5));
    }
}
