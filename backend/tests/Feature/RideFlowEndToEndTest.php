<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API-level end-to-end test: seeded accounts, real logins and bearer tokens,
 * the whole ride lifecycle through HTTP only.
 */
class RideFlowEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_ride_from_booking_to_drop_off(): void
    {
        $this->seed(DatabaseSeeder::class);

        $riderToken = $this->login('rider', 'rider@example.com');
        $driverToken = $this->login('driver', 'driver@example.com');
        $rivalToken = $this->login('driver', 'driver2@example.com');

        $locations = $this->getJson('/api/locations')->assertOk()->json('data');
        $this->assertNotEmpty($locations);

        $estimate = $this->withToken($riderToken)->postJson('/api/fare-estimates', $this->rideBody())->assertOk();
        $economyFare = $estimate->json('data.0.estimated_fare');

        $rideId = $this->withToken($riderToken)
            ->postJson('/api/rides', $this->rideBody(), ['Idempotency-Key' => 'e2e-booking-0001'])
            ->assertCreated()
            ->assertJsonPath('data.fare.estimated', $economyFare)
            ->json('data.id');

        $feed = $this->withToken($driverToken)->getJson('/api/driver/rides/available')->assertOk();
        $this->assertContains($rideId, array_column($feed->json('data'), 'id'));

        $this->withToken($driverToken)->postJson("/api/rides/{$rideId}/accept")->assertOk();
        $this->withToken($rivalToken)->postJson("/api/rides/{$rideId}/accept")->assertConflict();

        $this->withToken($riderToken)->getJson("/api/rides/{$rideId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.driver.name', 'Usman Tariq');

        $this->withToken($driverToken)->postJson("/api/rides/{$rideId}/start")->assertOk();
        $this->withToken($driverToken)->postJson("/api/rides/{$rideId}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.fare.final', $economyFare);

        $this->withToken($riderToken)->getJson('/api/rides')->assertOk()->assertJsonPath('data.0.status', 'completed');
    }

    private function login(string $role, string $email): string
    {
        $token = $this->postJson("/api/auth/{$role}/login", ['email' => $email, 'password' => 'password'])
            ->assertOk()
            ->json('data.token');
        // Each call must authenticate from its own token, not a cached guard user.
        $this->app['auth']->forgetGuards();

        return $token;
    }

    public function withToken(string $token, string $type = 'Bearer')
    {
        $this->app['auth']->forgetGuards();

        return parent::withToken($token, $type);
    }
}
