<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Ride;
use App\Models\Rider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RideLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function acceptedRide(?Driver $driver = null): array
    {
        $ride = Ride::factory()->create();
        $driver = $this->actingAsDriver($driver);
        $this->postJson("/api/rides/{$ride->id}/accept")->assertOk();

        return [$ride, $driver];
    }

    public function test_assigned_driver_starts_and_completes_the_ride(): void
    {
        [$ride, $driver] = $this->acceptedRide();

        $this->postJson("/api/rides/{$ride->id}/start")->assertOk()->assertJsonPath('data.status', 'in_progress');

        $this->postJson("/api/rides/{$ride->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.fare.final', '580.00')
            ->assertJsonPath('data.can_cancel', false);

        $driver->refresh();
        $this->assertSame('available', $driver->availability_status->value);
        // Driver is now where the trip ended.
        $this->assertEqualsWithDelta($ride->destination_latitude, $driver->current_latitude, 0.00001);
        $this->assertDatabaseHas('driver_assignments', ['ride_id' => $ride->id, 'status' => 'completed']);
    }

    public function test_only_the_assigned_driver_can_start_or_complete(): void
    {
        [$ride] = $this->acceptedRide();

        $this->actingAsDriver();
        $this->postJson("/api/rides/{$ride->id}/start")->assertForbidden()->assertJsonPath('error.code', 'NOT_ASSIGNED_DRIVER');
        $this->postJson("/api/rides/{$ride->id}/complete")->assertForbidden()->assertJsonPath('error.code', 'NOT_ASSIGNED_DRIVER');
        $this->assertSame('accepted', $ride->fresh()->status->value);
    }

    public function test_ride_cannot_be_completed_before_it_starts(): void
    {
        [$ride] = $this->acceptedRide();

        $this->postJson("/api/rides/{$ride->id}/complete")
            ->assertConflict()
            ->assertJsonPath('error.code', 'INVALID_RIDE_TRANSITION')
            ->assertJsonPath('error.details.allowed_transitions', ['in_progress', 'cancelled']);
    }

    public function test_requested_ride_cannot_be_started_or_completed(): void
    {
        $ride = Ride::factory()->create();
        $this->actingAsDriver();

        $this->postJson("/api/rides/{$ride->id}/start")->assertConflict()->assertJsonPath('error.code', 'INVALID_RIDE_TRANSITION');
        $this->postJson("/api/rides/{$ride->id}/complete")->assertConflict()->assertJsonPath('error.code', 'INVALID_RIDE_TRANSITION');
    }

    public function test_completed_ride_cannot_be_cancelled_or_completed_again(): void
    {
        [$ride] = $this->acceptedRide();
        $this->postJson("/api/rides/{$ride->id}/start")->assertOk();
        $this->postJson("/api/rides/{$ride->id}/complete")->assertOk();

        $this->postJson("/api/rides/{$ride->id}/complete")->assertConflict();

        $this->actingAsRider($ride->rider);
        $this->postJson("/api/rides/{$ride->id}/cancel")
            ->assertConflict()
            ->assertJsonPath('error.details.current_status', 'completed');
    }

    public function test_rider_cancels_a_requested_ride(): void
    {
        $ride = Ride::factory()->create();
        $this->actingAsRider($ride->rider);

        $this->postJson("/api/rides/{$ride->id}/cancel", ['reason' => 'Changed my plans'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Changed my plans')
            ->assertJsonPath('data.can_cancel', false);
    }

    public function test_cancelling_an_accepted_ride_releases_the_driver(): void
    {
        [$ride, $driver] = $this->acceptedRide();

        $this->actingAsRider($ride->rider);
        $this->postJson("/api/rides/{$ride->id}/cancel")->assertOk()->assertJsonPath('data.driver', null);

        $this->assertSame('available', $driver->fresh()->availability_status->value);
        $this->assertDatabaseHas('driver_assignments', ['ride_id' => $ride->id, 'status' => 'cancelled']);

        // The released driver can immediately take another ride.
        $next = Ride::factory()->create();
        $this->actingAsDriver($driver);
        $this->postJson("/api/rides/{$next->id}/accept")->assertOk();
    }

    public function test_ride_in_progress_cannot_be_cancelled(): void
    {
        [$ride] = $this->acceptedRide();
        $this->postJson("/api/rides/{$ride->id}/start")->assertOk();

        $this->actingAsRider($ride->rider);
        $this->postJson("/api/rides/{$ride->id}/cancel")->assertConflict()->assertJsonPath('error.code', 'INVALID_RIDE_TRANSITION');
    }

    public function test_rider_cannot_cancel_someone_elses_ride(): void
    {
        $ride = Ride::factory()->create();
        $this->actingAsRider(Rider::factory()->create());

        $this->postJson("/api/rides/{$ride->id}/cancel")->assertForbidden()->assertJsonPath('error.code', 'NOT_RIDE_OWNER');
        $this->assertSame('requested', $ride->fresh()->status->value);
    }

    public function test_driver_sees_their_current_ride(): void
    {
        $this->actingAsDriver();
        $this->getJson('/api/driver/rides/current')->assertOk()->assertJsonPath('data', null);

        [$ride] = $this->acceptedRide();
        $this->getJson('/api/driver/rides/current')->assertOk()->assertJsonPath('data.id', $ride->id);
    }
}
