<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Models\Driver;
use App\Models\DriverAssignment;
use App\Models\Ride;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_driver_accepts_a_requested_ride(): void
    {
        $ride = Ride::factory()->create();
        $driver = $this->actingAsDriver();

        $this->postJson("/api/rides/{$ride->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.driver.id', $driver->id)
            ->assertJsonPath('data.driver.vehicle.plate', $driver->vehicle_plate);

        $this->assertDatabaseHas('driver_assignments', [
            'ride_id' => $ride->id,
            'driver_id' => $driver->id,
            'status' => 'active',
        ]);
        $this->assertSame('busy', $driver->fresh()->availability_status->value);
        $this->assertNotNull($ride->fresh()->accepted_at);
    }

    public function test_second_driver_cannot_accept_an_already_assigned_ride(): void
    {
        $ride = Ride::factory()->create();
        $this->actingAsDriver();
        $this->postJson("/api/rides/{$ride->id}/accept")->assertOk();

        $second = $this->actingAsDriver();
        $this->postJson("/api/rides/{$ride->id}/accept")
            ->assertConflict()
            ->assertJsonPath('error.code', 'RIDE_ALREADY_ASSIGNED');

        $this->assertSame(1, DriverAssignment::where('ride_id', $ride->id)->count());
        $this->assertSame('available', $second->fresh()->availability_status->value);
    }

    public function test_offline_driver_cannot_accept(): void
    {
        $ride = Ride::factory()->create();
        $this->actingAsDriver(Driver::factory()->offline()->create());

        $this->postJson("/api/rides/{$ride->id}/accept")
            ->assertConflict()
            ->assertJsonPath('error.code', 'DRIVER_UNAVAILABLE');
        $this->assertDatabaseCount('driver_assignments', 0);
    }

    public function test_driver_on_a_ride_cannot_accept_another(): void
    {
        [$first, $second] = Ride::factory()->count(2)->create();
        $this->actingAsDriver();
        $this->postJson("/api/rides/{$first->id}/accept")->assertOk();

        $this->postJson("/api/rides/{$second->id}/accept")
            ->assertConflict()
            ->assertJsonPath('error.code', 'DRIVER_HAS_ACTIVE_RIDE');
        $this->assertSame('requested', $second->fresh()->status->value);
    }

    public function test_driver_with_wrong_vehicle_category_is_not_eligible(): void
    {
        $ride = Ride::factory()->comfort()->create();
        $this->actingAsDriver(); // economy

        $this->postJson("/api/rides/{$ride->id}/accept")
            ->assertConflict()
            ->assertJsonPath('error.code', 'DRIVER_NOT_ELIGIBLE');
    }

    public function test_cancelled_ride_cannot_be_accepted(): void
    {
        $ride = Ride::factory()->create(['status' => 'cancelled', 'cancelled_at' => now()]);
        $this->actingAsDriver();

        $this->postJson("/api/rides/{$ride->id}/accept")
            ->assertConflict()
            ->assertJsonPath('error.code', 'INVALID_RIDE_TRANSITION')
            ->assertJsonPath('error.details.current_status', 'cancelled');
    }

    public function test_feed_lists_only_eligible_nearby_requests_nearest_first(): void
    {
        $near = Ride::factory()->create(['pickup_latitude' => 31.5160, 'pickup_longitude' => 74.3510]); // MM Alam Road
        $far = Ride::factory()->create(['pickup_latitude' => 31.4830, 'pickup_longitude' => 74.3240]); // Model Town, ~4 km
        Ride::factory()->comfort()->create(); // wrong category
        Ride::factory()->create(['pickup_latitude' => 31.4697, 'pickup_longitude' => 74.2728]); // Johar Town, ~8.4 km: outside radius
        $this->actingAsDriver(Driver::factory()->at(31.5150, 74.3480)->create());

        $response = $this->getJson('/api/driver/rides/available')->assertOk();

        $this->assertSame([$near->id, $far->id], array_column($response->json('data'), 'id'));
        $this->assertLessThan(1, $response->json('data.0.pickup_distance_km'));
    }

    public function test_offline_driver_sees_an_empty_feed(): void
    {
        Ride::factory()->create();
        $this->actingAsDriver(Driver::factory()->offline()->create());

        $this->getJson('/api/driver/rides/available')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.availability_status', 'offline');
    }

    public function test_driver_can_toggle_availability_but_not_while_busy(): void
    {
        $driver = $this->actingAsDriver(Driver::factory()->offline()->create());
        $this->putJson('/api/driver/availability', ['status' => 'available'])
            ->assertOk()->assertJsonPath('data.availability_status', 'available');

        $this->putJson('/api/driver/availability', ['status' => 'busy'])->assertUnprocessable();

        $ride = Ride::factory()->create();
        $this->postJson("/api/rides/{$ride->id}/accept")->assertOk();
        $this->putJson('/api/driver/availability', ['status' => 'offline'])
            ->assertConflict()->assertJsonPath('error.code', 'DRIVER_ON_RIDE');
        $this->assertSame('busy', $driver->fresh()->availability_status->value);
    }

    public function test_schema_forbids_two_active_assignments_for_one_ride(): void
    {
        $ride = Ride::factory()->create();
        [$a, $b] = Driver::factory()->count(2)->create();
        DriverAssignment::create(['ride_id' => $ride->id, 'driver_id' => $a->id, 'status' => AssignmentStatus::Active, 'assigned_at' => now()]);

        $this->expectException(UniqueConstraintViolationException::class);
        DriverAssignment::create(['ride_id' => $ride->id, 'driver_id' => $b->id, 'status' => AssignmentStatus::Active, 'assigned_at' => now()]);
    }

    public function test_schema_forbids_two_active_assignments_for_one_driver(): void
    {
        [$r1, $r2] = Ride::factory()->count(2)->create();
        $driver = Driver::factory()->create();
        DriverAssignment::create(['ride_id' => $r1->id, 'driver_id' => $driver->id, 'status' => AssignmentStatus::Active, 'assigned_at' => now()]);

        $this->expectException(UniqueConstraintViolationException::class);
        DriverAssignment::create(['ride_id' => $r2->id, 'driver_id' => $driver->id, 'status' => AssignmentStatus::Active, 'assigned_at' => now()]);
    }

    public function test_finished_assignments_do_not_block_new_ones(): void
    {
        [$r1, $r2] = Ride::factory()->count(2)->create();
        $driver = Driver::factory()->create();
        DriverAssignment::create(['ride_id' => $r1->id, 'driver_id' => $driver->id, 'status' => AssignmentStatus::Completed, 'assigned_at' => now()]);
        DriverAssignment::create(['ride_id' => $r2->id, 'driver_id' => $driver->id, 'status' => AssignmentStatus::Active, 'assigned_at' => now()]);

        $this->assertDatabaseCount('driver_assignments', 2);
    }
}
