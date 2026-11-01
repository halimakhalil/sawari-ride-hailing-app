<?php

namespace Tests\Feature;

use App\Models\Rider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_with_same_key_returns_the_original_ride(): void
    {
        $this->actingAsRider();
        $headers = ['Idempotency-Key' => 'booking-7f3a9c21'];

        $first = $this->postJson('/api/rides', $this->rideBody(), $headers)
            ->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'false');

        $retry = $this->postJson('/api/rides', $this->rideBody(), $headers)
            ->assertOk()
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($first->json('data.id'), $retry->json('data.id'));
        $this->assertDatabaseCount('rides', 1);
        $this->assertDatabaseHas('idempotency_keys', [
            'idempotency_key' => 'booking-7f3a9c21',
            'ride_id' => $first->json('data.id'),
            'response_status' => 201,
        ]);
    }

    public function test_replay_returns_current_state_of_the_original_ride(): void
    {
        $this->actingAsRider();
        $headers = ['Idempotency-Key' => 'booking-cancelled-1'];

        $id = $this->postJson('/api/rides', $this->rideBody(), $headers)->json('data.id');
        $this->postJson("/api/rides/{$id}/cancel")->assertOk();

        // A late retry must not resurrect the booking as a new ride.
        $this->postJson('/api/rides', $this->rideBody(), $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.status', 'cancelled');
        $this->assertDatabaseCount('rides', 1);
    }

    public function test_reusing_a_key_with_a_different_body_is_rejected(): void
    {
        $this->actingAsRider();
        $headers = ['Idempotency-Key' => 'booking-reused-01'];

        $this->postJson('/api/rides', $this->rideBody(), $headers)->assertCreated();

        $this->postJson('/api/rides', $this->rideBody(['category' => 'comfort']), $headers)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REUSED');
        $this->assertDatabaseCount('rides', 1);
    }

    public function test_keys_are_scoped_per_rider(): void
    {
        $headers = ['Idempotency-Key' => 'shared-key-0001'];

        Sanctum::actingAs(Rider::factory()->create(), ['rider']);
        $a = $this->postJson('/api/rides', $this->rideBody(), $headers)->assertCreated()->json('data.id');

        Sanctum::actingAs(Rider::factory()->create(), ['rider']);
        $b = $this->postJson('/api/rides', $this->rideBody(), $headers)->assertCreated()->json('data.id');

        $this->assertNotSame($a, $b);
        $this->assertDatabaseCount('rides', 2);
    }

    public function test_failed_request_does_not_consume_the_key(): void
    {
        $this->actingAsRider();
        $this->postJson('/api/rides', $this->rideBody())->assertCreated();
        $headers = ['Idempotency-Key' => 'booking-after-conflict'];

        // Rejected because of the active ride: the key claim rolls back with it.
        $this->postJson('/api/rides', $this->rideBody(), $headers)->assertConflict();
        $this->assertDatabaseMissing('idempotency_keys', ['idempotency_key' => 'booking-after-conflict']);
    }

    public function test_malformed_key_is_rejected(): void
    {
        $this->actingAsRider();

        $this->postJson('/api/rides', $this->rideBody(), ['Idempotency-Key' => 'bad key!'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key'], 'error.details');
    }
}
