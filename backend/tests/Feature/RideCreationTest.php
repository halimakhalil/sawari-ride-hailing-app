<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Ride;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RideCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rider_can_request_a_ride(): void
    {
        Driver::factory()->create(); // available economy driver in Gulberg
        $rider = $this->actingAsRider();

        $response = $this->postJson('/api/rides', $this->rideBody());

        $response->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'false')
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.category', 'economy')
            ->assertJsonPath('data.pickup.address', 'Liberty Market, Gulberg')
            ->assertJsonPath('data.fare.currency', 'PKR')
            ->assertJsonPath('data.fare.estimated', '600.00')
            ->assertJsonPath('data.fare.final', null)
            ->assertJsonPath('data.can_cancel', true)
            ->assertJsonPath('data.driver', null);

        $this->assertIsInt($response->json('data.estimated_pickup_minutes'));
        $this->assertDatabaseHas('rides', [
            'rider_id' => $rider->id,
            'status' => 'requested',
            'estimated_fare' => 600.00,
        ]);
    }

    public function test_fare_is_calculated_on_the_server_and_client_values_are_ignored(): void
    {
        $this->actingAsRider();

        $response = $this->postJson('/api/rides', $this->rideBody([
            'estimated_fare' => 1,
            'fare' => ['estimated' => '1.00'],
            'status' => 'completed',
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.fare.estimated', '600.00')
            ->assertJsonPath('data.status', 'requested');
    }

    public function test_pickup_eta_is_null_when_no_driver_is_nearby(): void
    {
        Driver::factory()->offline()->create();
        $this->actingAsRider();

        $this->postJson('/api/rides', $this->rideBody())
            ->assertCreated()
            ->assertJsonPath('data.estimated_pickup_minutes', null);
    }

    public function test_fare_estimates_quote_both_categories(): void
    {
        $this->actingAsRider();

        $this->postJson('/api/fare-estimates', $this->rideBody())
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.category', 'economy')
            ->assertJsonPath('data.0.estimated_fare', '600.00')
            ->assertJsonPath('data.1.category', 'comfort')
            ->assertJsonPath('data.1.estimated_fare', '870.00');
    }

    /** @return array<string, array{array<string, mixed>, list<string>}> */
    public static function invalidBodies(): array
    {
        return [
            'missing everything' => [[], ['pickup', 'destination', 'category']],
            'unknown category' => [['category' => 'luxury'], ['category']],
            'pickup outside Lahore' => [['pickup' => ['latitude' => 24.86, 'longitude' => 67.0]], ['pickup.latitude', 'pickup.longitude']],
            'non-numeric coordinates' => [['destination' => ['latitude' => 'abc']], ['destination.latitude']],
            'markup in address' => [['pickup' => ['address' => '<script>alert(1)</script>']], ['pickup.address']],
            'empty address' => [['destination' => ['address' => '']], ['destination.address']],
            'same pickup and destination' => [['destination' => ['latitude' => 31.5104, 'longitude' => 74.3446]], ['destination']],
        ];
    }

    #[DataProvider('invalidBodies')]
    public function test_invalid_input_is_rejected(array $overrides, array $expectedErrors): void
    {
        $this->actingAsRider();
        $body = $overrides === [] ? [] : $this->rideBody($overrides);

        $this->postJson('/api/rides', $body)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['code', 'message', 'details', 'request_id']])
            ->assertJsonValidationErrors($expectedErrors, 'error.details');

        $this->assertDatabaseCount('rides', 0);
    }

    public function test_rider_cannot_have_two_active_rides(): void
    {
        $this->actingAsRider();

        $first = $this->postJson('/api/rides', $this->rideBody())->assertCreated();

        $this->postJson('/api/rides', $this->rideBody(['category' => 'comfort']))
            ->assertConflict()
            ->assertJsonPath('error.code', 'ACTIVE_RIDE_EXISTS')
            ->assertJsonPath('error.details.active_ride_id', $first->json('data.id'));

        $this->assertDatabaseCount('rides', 1);
    }

    public function test_database_rejects_a_second_active_ride_for_a_rider(): void
    {
        $ride = Ride::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Ride::factory()->create(['rider_id' => $ride->rider_id]);
    }

    public function test_database_rejects_a_negative_fare(): void
    {
        $this->expectException(QueryException::class);

        Ride::factory()->create(['estimated_fare' => -10]);
    }

    public function test_rider_can_request_again_after_previous_ride_is_cancelled(): void
    {
        $this->actingAsRider();
        $id = $this->postJson('/api/rides', $this->rideBody())->json('data.id');
        $this->postJson("/api/rides/{$id}/cancel")->assertOk();

        $this->postJson('/api/rides', $this->rideBody())->assertCreated();
    }

    public function test_rider_sees_only_their_own_rides(): void
    {
        $other = Ride::factory()->create();
        $this->actingAsRider();
        $mine = $this->postJson('/api/rides', $this->rideBody())->json('data.id');

        $this->getJson('/api/rides')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine);
        $this->getJson("/api/rides/{$mine}")->assertOk();
        $this->getJson("/api/rides/{$other->id}")->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');
        $this->getJson('/api/rides/999999')->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
    }
}
