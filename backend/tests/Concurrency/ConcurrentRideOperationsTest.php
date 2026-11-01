<?php

namespace Tests\Concurrency;

use App\Models\Driver;
use App\Models\DriverAssignment;
use App\Models\IdempotencyKey;
use App\Models\Ride;
use App\Models\Rider;
use App\Services\RideService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Real concurrency: every competing request runs in its own PHP process with its
 * own MySQL connection, so InnoDB row locks and unique indexes are genuinely
 * exercised. RefreshDatabase cannot be used because its wrapping transaction
 * would hide the fixtures from the worker processes; data is committed and the
 * tables are truncated around each test instead.
 */
class ConcurrentRideOperationsTest extends TestCase
{
    private const TABLES = ['idempotency_keys', 'driver_assignments', 'rides', 'drivers', 'riders', 'personal_access_tokens'];

    /** Seconds allowed for every worker to boot before the barrier releases them. */
    private const BARRIER_DELAY = 3.0;

    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrated) {
            $this->artisan('migrate:fresh');
            self::$migrated = true;
        }

        $this->truncate();
    }

    protected function tearDown(): void
    {
        $this->truncate();
        parent::tearDown();
    }

    public function test_many_drivers_racing_for_one_ride_produce_exactly_one_assignment(): void
    {
        $ride = Ride::factory()->create();
        $drivers = Driver::factory()->count(8)->create();

        $results = $this->runTogether($drivers->map(fn (Driver $d) => ['accept', $ride->id, $d->id])->all());

        $winners = array_filter($results, fn ($r) => $r['ok']);
        $losers = array_filter($results, fn ($r) => ! $r['ok']);

        $this->assertCount(1, $winners, 'Exactly one driver must win: '.json_encode($results));
        $this->assertSame(['RIDE_ALREADY_ASSIGNED'], array_values(array_unique(array_column($losers, 'code'))));
        $this->assertSame(1, DriverAssignment::where('ride_id', $ride->id)->count());
        $this->assertSame(1, Driver::where('availability_status', 'busy')->count());
        $this->assertSame('accepted', $ride->fresh()->status->value);
    }

    public function test_one_driver_racing_to_accept_two_rides_gets_only_one(): void
    {
        [$first, $second] = Ride::factory()->count(2)->create();
        $driver = Driver::factory()->create();

        $results = $this->runTogether([
            ['accept', $first->id, $driver->id],
            ['accept', $second->id, $driver->id],
        ]);

        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']), json_encode($results));
        $failure = array_values(array_filter($results, fn ($r) => ! $r['ok']))[0];
        $this->assertSame('DRIVER_HAS_ACTIVE_RIDE', $failure['code']);
        $this->assertSame(1, DriverAssignment::where('driver_id', $driver->id)->count());
    }

    public function test_a_blocked_accept_waits_for_the_row_lock_then_sees_the_ride_taken(): void
    {
        $ride = Ride::factory()->create();
        [$winner, $loser] = Driver::factory()->count(2)->create();

        // Hold the ride's row lock from this process, as an in-flight accept would.
        DB::beginTransaction();
        Ride::query()->lockForUpdate()->findOrFail($ride->id);

        $worker = $this->worker(['accept', $ride->id, $loser->id, '0']);
        $worker->start();
        usleep(1_500_000);

        $this->assertTrue($worker->isRunning(), 'Competing accept should be blocked on the ride row lock. Output: '.$worker->getOutput().$worker->getErrorOutput());

        app(RideService::class)->accept($ride->id, $winner); // runs inside our transaction (savepoint)
        DB::commit();

        $worker->wait();
        $result = json_decode($worker->getOutput(), true);

        $this->assertFalse($result['ok'] ?? true, $worker->getOutput().$worker->getErrorOutput());
        $this->assertSame('RIDE_ALREADY_ASSIGNED', $result['code']);
        $this->assertSame([$winner->id], DriverAssignment::where('ride_id', $ride->id)->pluck('driver_id')->all());
        $this->assertSame('available', $loser->fresh()->availability_status->value);
    }

    public function test_simultaneous_retries_with_one_idempotency_key_create_one_ride(): void
    {
        $rider = Rider::factory()->create();

        $results = $this->runTogether(array_fill(0, 6, ['create', $rider->id, 'double-tap-key-001']));

        $this->assertTrue(collect($results)->every(fn ($r) => $r['ok']), 'Every retry should succeed: '.json_encode($results));
        $this->assertCount(1, array_unique(array_column($results, 'ride_id')));
        $this->assertSame(1, collect($results)->where('replayed', false)->count());
        $this->assertSame(1, Ride::count());
        $this->assertSame(1, IdempotencyKey::count());
    }

    public function test_simultaneous_requests_without_a_key_still_create_one_active_ride(): void
    {
        $rider = Rider::factory()->create();

        $results = $this->runTogether(array_fill(0, 5, ['create', $rider->id, '-']));

        $this->assertCount(1, array_filter($results, fn ($r) => $r['ok']), json_encode($results));
        $this->assertSame(['ACTIVE_RIDE_EXISTS'], array_values(array_unique(array_column(array_filter($results, fn ($r) => ! $r['ok']), 'code'))));
        $this->assertSame(1, Ride::count());
    }

    /**
     * Start one worker per job, release them all at the same instant and collect results.
     *
     * @param  list<array{string, int, int|string}>  $jobs
     * @return list<array<string, mixed>>
     */
    private function runTogether(array $jobs): array
    {
        $startAt = sprintf('%.6F', microtime(true) + self::BARRIER_DELAY);
        $workers = array_map(fn (array $job) => $this->worker([...$job, $startAt]), $jobs);

        foreach ($workers as $worker) {
            $worker->start();
        }

        return array_map(function (Process $worker) {
            $worker->wait();
            $result = json_decode($worker->getOutput(), true);

            $this->assertIsArray($result, 'Worker produced no result: '.$worker->getOutput().$worker->getErrorOutput());
            $this->assertNotSame('UNEXPECTED', $result['code'] ?? null, $result['message'] ?? '');
            // If a worker booted after the barrier the race was not real; fail loudly instead of passing vacuously.
            $this->assertTrue($result['ready_before_barrier'], 'Worker was not ready before the barrier; raise BARRIER_DELAY.');

            return $result;
        }, $workers);
    }

    /** @param list<int|string> $args */
    private function worker(array $args): Process
    {
        $db = config('database.connections.mysql');

        return new Process([PHP_BINARY, __DIR__.'/worker.php', ...array_map('strval', $args)], base_path(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $db['host'],
            'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => (string) $db['password'],
            'CACHE_STORE' => 'array',
            'LOG_CHANNEL' => 'null',
        ], null, 60);
    }

    private function truncate(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            foreach (self::TABLES as $table) {
                DB::table($table)->truncate();
            }
        });
    }
}
