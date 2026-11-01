<?php

namespace App\Services;

use App\Data\RideRequestData;
use App\Enums\AssignmentStatus;
use App\Enums\DriverAvailability;
use App\Enums\RideStatus;
use App\Exceptions\ApiException;
use App\Exceptions\InvalidRideTransitionException;
use App\Models\Driver;
use App\Models\DriverAssignment;
use App\Models\IdempotencyKey;
use App\Models\Ride;
use App\Models\Rider;
use App\Support\GeoPoint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ride lifecycle use cases. Every state change runs in a single InnoDB
 * transaction that locks the rows it reads with SELECT ... FOR UPDATE.
 *
 * Lock order is always: ride → driver → assignment. Keeping a single global
 * order prevents lock-ordering deadlocks between accept / cancel / complete.
 * Transactions are retried on deadlock (DB::transaction attempts), and the
 * UNIQUE indexes on generated "active_*" columns back the locks up at the
 * schema level.
 */
class RideService
{
    private const TRANSACTION_ATTEMPTS = 3;

    public function __construct(
        private readonly FareCalculator $fares,
        private readonly DriverMatchingService $matching,
    ) {}

    /**
     * Create a ride request. With an idempotency key, a retry of the same request
     * returns the ride created by the first attempt instead of creating another.
     */
    public function requestRide(Rider $rider, RideRequestData $data, ?string $idempotencyKey = null): RideRequestResult
    {
        $fingerprint = $data->fingerprint();

        if ($idempotencyKey !== null && $replay = $this->findReplay($rider, $idempotencyKey, $fingerprint)) {
            return $replay;
        }

        try {
            $ride = DB::transaction(function () use ($rider, $data, $idempotencyKey, $fingerprint) {
                // Claim the key first. A concurrent request with the same key blocks on
                // the UNIQUE index until this transaction ends, then fails with a
                // duplicate-key error and replays our committed result below.
                $key = $idempotencyKey === null ? null : IdempotencyKey::create([
                    'rider_id' => $rider->id,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $fingerprint,
                ]);

                // Serialise ride creation per rider so the active-ride check cannot race.
                Rider::query()->whereKey($rider->id)->lockForUpdate()->firstOrFail();

                $active = Ride::query()->where('rider_id', $rider->id)->active()->lockForUpdate()->first();
                if ($active !== null) {
                    throw $this->activeRideExists($active->id);
                }

                $quote = $this->fares->quote($data->pickup, $data->destination, $data->category);

                $ride = Ride::create([
                    'rider_id' => $rider->id,
                    'category' => $data->category,
                    'status' => RideStatus::Requested,
                    'pickup_address' => $data->pickupAddress,
                    'pickup_latitude' => $data->pickup->latitude,
                    'pickup_longitude' => $data->pickup->longitude,
                    'destination_address' => $data->destinationAddress,
                    'destination_latitude' => $data->destination->latitude,
                    'destination_longitude' => $data->destination->longitude,
                    'distance_km' => $quote->distanceKm,
                    'currency' => $quote->currency,
                    'fare_multiplier' => $quote->multiplier,
                    'estimated_fare' => $quote->amount,
                    'estimated_pickup_minutes' => $this->matching->estimatePickupMinutes($data->pickup, $data->category),
                    'requested_at' => now(),
                ]);

                $key?->update(['ride_id' => $ride->id, 'response_status' => 201]);

                return $ride;
            }, self::TRANSACTION_ATTEMPTS);
        } catch (UniqueConstraintViolationException $e) {
            if ($idempotencyKey !== null && $replay = $this->findReplay($rider, $idempotencyKey, $fingerprint)) {
                return $replay;
            }

            // Otherwise the rides_one_active_ride_per_rider index fired.
            throw $this->activeRideExists(Ride::query()->where('rider_id', $rider->id)->active()->value('id'));
        }

        Log::info('ride.requested', [
            'ride_id' => $ride->id,
            'rider_id' => $rider->id,
            'category' => $ride->category->value,
            'estimated_fare' => $ride->estimated_fare,
        ]);

        return new RideRequestResult($ride, replayed: false);
    }

    /** Driver accepts an open ride. Exactly one driver can win; the others get 409. */
    public function accept(int $rideId, Driver $driver): Ride
    {
        try {
            $ride = DB::transaction(function () use ($rideId, $driver) {
                $ride = Ride::query()->lockForUpdate()->findOrFail($rideId);

                if ($ride->status !== RideStatus::Requested) {
                    throw $ride->status->isActive()
                        ? $this->alreadyAssigned()
                        : new InvalidRideTransitionException($ride->status, RideStatus::Accepted);
                }

                $lockedDriver = Driver::query()->lockForUpdate()->findOrFail($driver->id);

                if ($lockedDriver->vehicle_category !== $ride->category) {
                    throw ApiException::conflict(
                        'DRIVER_NOT_ELIGIBLE',
                        "This ride needs a {$ride->category->label()} vehicle.",
                    );
                }

                // Every write to a driver's assignments happens while holding that
                // driver's row lock (acquired above), so this read is current. The
                // assignments_one_active_per_driver index is the schema-level backstop.
                $hasActiveRide = DriverAssignment::query()
                    ->where('driver_id', $lockedDriver->id)
                    ->where('status', AssignmentStatus::Active)
                    ->exists();

                if ($hasActiveRide) {
                    throw ApiException::conflict('DRIVER_HAS_ACTIVE_RIDE', 'Finish your current ride before accepting another.');
                }

                if (! $lockedDriver->isAvailable()) {
                    throw ApiException::conflict('DRIVER_UNAVAILABLE', 'Go online before accepting rides.');
                }

                DriverAssignment::create([
                    'ride_id' => $ride->id,
                    'driver_id' => $lockedDriver->id,
                    'status' => AssignmentStatus::Active,
                    'assigned_at' => now(),
                ]);

                $this->transition($ride, RideStatus::Accepted);
                $ride->accepted_at = now();
                if ($lockedDriver->hasLocation()) {
                    // Replace the request-time estimate with the ETA of the driver who actually accepted.
                    $driverPosition = new GeoPoint($lockedDriver->current_latitude, $lockedDriver->current_longitude);
                    $ride->estimated_pickup_minutes = $this->matching->minutesForDistance(
                        $driverPosition->distanceTo(new GeoPoint($ride->pickup_latitude, $ride->pickup_longitude)),
                    );
                }
                $ride->save();

                $lockedDriver->update(['availability_status' => DriverAvailability::Busy]);

                return $ride;
            }, self::TRANSACTION_ATTEMPTS);
        } catch (UniqueConstraintViolationException) {
            // Only reachable if the locks above were bypassed; the schema still refuses.
            throw $this->alreadyAssigned();
        }

        Log::info('ride.accepted', ['ride_id' => $ride->id, 'driver_id' => $driver->id]);

        return $ride;
    }

    /** Assigned driver has picked the rider up. */
    public function start(int $rideId, Driver $driver): Ride
    {
        $ride = DB::transaction(function () use ($rideId, $driver) {
            $ride = Ride::query()->lockForUpdate()->findOrFail($rideId);
            $this->ensureAssignedDriver($ride, $driver);

            $this->transition($ride, RideStatus::InProgress);
            $ride->started_at = now();
            $ride->save();

            return $ride;
        }, self::TRANSACTION_ATTEMPTS);

        Log::info('ride.started', ['ride_id' => $ride->id, 'driver_id' => $driver->id]);

        return $ride;
    }

    /** Assigned driver drops the rider off. Frees the driver and fixes the fare. */
    public function complete(int $rideId, Driver $driver): Ride
    {
        $ride = DB::transaction(function () use ($rideId, $driver) {
            $ride = Ride::query()->lockForUpdate()->findOrFail($rideId);
            $this->ensureAssignedDriver($ride, $driver);
            $this->transition($ride, RideStatus::Completed);

            $lockedDriver = Driver::query()->lockForUpdate()->findOrFail($driver->id);
            $assignment = $this->activeAssignmentOf($ride);

            $ride->completed_at = now();
            // No trip tracking in this demo, so the final fare is the quoted fare.
            $ride->final_fare = $ride->estimated_fare;
            $ride->save();

            $assignment->update(['status' => AssignmentStatus::Completed, 'completed_at' => now()]);

            $lockedDriver->update([
                'availability_status' => DriverAvailability::Available,
                'current_latitude' => $ride->destination_latitude,
                'current_longitude' => $ride->destination_longitude,
                'location_updated_at' => now(),
            ]);

            return $ride;
        }, self::TRANSACTION_ATTEMPTS);

        Log::info('ride.completed', ['ride_id' => $ride->id, 'driver_id' => $driver->id, 'final_fare' => $ride->final_fare]);

        return $ride;
    }

    /** Rider cancels their own ride before the trip starts. Frees any assigned driver. */
    public function cancel(int $rideId, Rider $rider, ?string $reason = null): Ride
    {
        $ride = DB::transaction(function () use ($rideId, $rider, $reason) {
            $ride = Ride::query()->lockForUpdate()->findOrFail($rideId);

            if ($ride->rider_id !== $rider->id) {
                throw ApiException::forbidden('NOT_RIDE_OWNER', 'You can only cancel your own rides.');
            }

            $this->transition($ride, RideStatus::Cancelled);

            // Under the ride lock this ride's assignment cannot change, so a plain
            // read is enough to learn which driver row to lock next.
            $assignment = $ride->activeAssignment()->first();
            if ($assignment !== null) {
                $driver = Driver::query()->lockForUpdate()->findOrFail($assignment->driver_id);
                $assignment->update(['status' => AssignmentStatus::Cancelled, 'cancelled_at' => now()]);
                $driver->update(['availability_status' => DriverAvailability::Available]);
            }

            $ride->cancelled_at = now();
            $ride->cancellation_reason = $reason;
            $ride->save();

            return $ride;
        }, self::TRANSACTION_ATTEMPTS);

        Log::info('ride.cancelled', ['ride_id' => $ride->id, 'rider_id' => $rider->id]);

        return $ride;
    }

    /** Driver goes online or offline. Busy is system-managed and cannot be set or left manually. */
    public function setDriverAvailability(Driver $driver, DriverAvailability $status): Driver
    {
        return DB::transaction(function () use ($driver, $status) {
            $locked = Driver::query()->lockForUpdate()->findOrFail($driver->id);

            if ($locked->availability_status === DriverAvailability::Busy) {
                throw ApiException::conflict('DRIVER_ON_RIDE', 'You cannot change availability during a ride.');
            }

            $locked->update(['availability_status' => $status]);
            Log::info('driver.availability_changed', ['driver_id' => $locked->id, 'status' => $status->value]);

            return $locked;
        }, self::TRANSACTION_ATTEMPTS);
    }

    private function transition(Ride $ride, RideStatus $next): void
    {
        if (! $ride->status->canTransitionTo($next)) {
            throw new InvalidRideTransitionException($ride->status, $next);
        }

        $ride->status = $next;
    }

    /** Only the driver of the ride's current assignment may drive its lifecycle. */
    private function ensureAssignedDriver(Ride $ride, Driver $driver): void
    {
        $assignment = $ride->currentAssignment()->first();

        if ($assignment !== null && $assignment->driver_id !== $driver->id) {
            throw ApiException::forbidden('NOT_ASSIGNED_DRIVER', 'Only the assigned driver can update this ride.');
        }
        // No assignment means the ride was never accepted; transition() rejects it with 409.
    }

    private function activeAssignmentOf(Ride $ride): DriverAssignment
    {
        return $ride->activeAssignment()->lockForUpdate()->firstOrFail();
    }

    private function findReplay(Rider $rider, string $idempotencyKey, string $fingerprint): ?RideRequestResult
    {
        $record = IdempotencyKey::query()
            ->where('rider_id', $rider->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($record === null || $record->ride_id === null) {
            return null;
        }

        if (! hash_equals($record->request_hash, $fingerprint)) {
            throw ApiException::unprocessable(
                'IDEMPOTENCY_KEY_REUSED',
                'This Idempotency-Key was already used with a different request body.',
            );
        }

        Log::info('ride.request_replayed', ['ride_id' => $record->ride_id, 'rider_id' => $rider->id]);

        return new RideRequestResult(Ride::query()->findOrFail($record->ride_id), replayed: true);
    }

    private function alreadyAssigned(): ApiException
    {
        return ApiException::conflict('RIDE_ALREADY_ASSIGNED', 'Another driver has already accepted this ride.');
    }

    private function activeRideExists(?int $rideId): ApiException
    {
        return ApiException::conflict(
            'ACTIVE_RIDE_EXISTS',
            'You already have a ride in progress. Cancel or finish it before requesting another.',
            ['active_ride_id' => $rideId],
        );
    }
}
