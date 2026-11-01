<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Ride;
use App\Models\Rider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_rider_logs_in_and_uses_the_bearer_token(): void
    {
        $rider = Rider::factory()->create(['email' => 'ayesha@example.com']);

        $token = $this->postJson('/api/auth/rider/login', ['email' => 'ayesha@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.role', 'rider')
            ->assertJsonPath('data.user.id', $rider->id)
            ->assertJsonMissingPath('data.user.password')
            ->json('data.token');

        $this->withToken($token)->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.role', 'rider');
    }

    public function test_rider_credentials_do_not_work_on_the_driver_login(): void
    {
        Rider::factory()->create(['email' => 'ayesha@example.com']);

        $this->postJson('/api/auth/driver/login', ['email' => 'ayesha@example.com', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_wrong_password_is_rejected(): void
    {
        Driver::factory()->create(['email' => 'usman@example.com']);

        $this->postJson('/api/auth/driver/login', ['email' => 'usman@example.com', 'password' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email'], 'error.details');
    }

    public function test_protected_routes_require_authentication(): void
    {
        $ride = Ride::factory()->create();

        $this->postJson('/api/rides', $this->rideBody())->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHENTICATED');
        $this->getJson("/api/rides/{$ride->id}")->assertUnauthorized();
        $this->postJson("/api/rides/{$ride->id}/accept")->assertUnauthorized();
        $this->withToken('1|not-a-real-token')->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_riders_cannot_use_driver_endpoints(): void
    {
        $ride = Ride::factory()->create();
        $this->actingAsRider($ride->rider);

        $this->postJson("/api/rides/{$ride->id}/accept")->assertForbidden()->assertJsonPath('error.code', 'WRONG_ACCOUNT_TYPE');
        $this->getJson('/api/driver/rides/available')->assertForbidden();
        $this->assertDatabaseCount('driver_assignments', 0);
    }

    public function test_drivers_cannot_book_or_cancel_rides(): void
    {
        $ride = Ride::factory()->create();
        $this->actingAsDriver();

        $this->postJson('/api/rides', $this->rideBody())->assertForbidden();
        $this->postJson("/api/rides/{$ride->id}/cancel")->assertForbidden();
    }

    public function test_driver_identity_comes_from_the_token_not_the_request_body(): void
    {
        $ride = Ride::factory()->create();
        $victim = Driver::factory()->create();
        $actor = $this->actingAsDriver();

        $this->postJson("/api/rides/{$ride->id}/accept", ['driver_id' => $victim->id])->assertOk();

        $this->assertDatabaseHas('driver_assignments', ['ride_id' => $ride->id, 'driver_id' => $actor->id]);
        $this->assertDatabaseMissing('driver_assignments', ['driver_id' => $victim->id]);
    }

    public function test_logout_revokes_the_token(): void
    {
        Rider::factory()->create(['email' => 'ayesha@example.com']);
        $token = $this->postJson('/api/auth/rider/login', ['email' => 'ayesha@example.com', 'password' => 'password'])->json('data.token');

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }
}
