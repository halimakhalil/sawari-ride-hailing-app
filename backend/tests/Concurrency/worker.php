<?php

/*
 * Child process used by the concurrency tests. Each worker boots the Laravel app
 * with its own database connection, waits for a shared start time (a barrier so
 * all workers fire together), runs one RideService call and prints a JSON result.
 *
 *   php worker.php accept <ride_id> <driver_id> <start_at>
 *   php worker.php create <rider_id> <idempotency_key|-> <start_at>
 */

use App\Data\RideRequestData;
use App\Exceptions\ApiException;
use App\Models\Driver;
use App\Models\Rider;
use App\Services\RideService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $action, $subjectId, $actorArg, $startAt] = $argv + [null, null, null, null, '0'];

// Open the connection before the barrier so connect time does not skew the race.
Illuminate\Support\Facades\DB::select('select 1');

$readyAt = microtime(true);
while (microtime(true) < (float) $startAt) {
    usleep(200);
}

$service = $app->make(RideService::class);
$result = ['ready_before_barrier' => $readyAt < (float) $startAt];

try {
    if ($action === 'accept') {
        $ride = $service->accept((int) $subjectId, Driver::findOrFail((int) $actorArg));
        $result += ['ok' => true, 'ride_id' => $ride->id];
    } elseif ($action === 'create') {
        $data = RideRequestData::fromArray([
            'pickup' => ['address' => 'Liberty Market', 'latitude' => 31.5104, 'longitude' => 74.3446],
            'destination' => ['address' => 'Johar Town', 'latitude' => 31.4697, 'longitude' => 74.2728],
            'category' => 'economy',
        ]);
        $outcome = $service->requestRide(Rider::findOrFail((int) $subjectId), $data, $actorArg === '-' ? null : $actorArg);
        $result += ['ok' => true, 'ride_id' => $outcome->ride->id, 'replayed' => $outcome->replayed];
    } else {
        throw new InvalidArgumentException("Unknown action {$action}");
    }
} catch (ApiException $e) {
    $result += ['ok' => false, 'code' => $e->errorCode];
} catch (Throwable $e) {
    $result += ['ok' => false, 'code' => 'UNEXPECTED', 'message' => $e::class.': '.$e->getMessage()];
}

echo json_encode($result);
