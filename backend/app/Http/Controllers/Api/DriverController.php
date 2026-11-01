<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDriverAvailabilityRequest;
use App\Http\Resources\DriverResource;
use App\Http\Resources\OpenRideResource;
use App\Http\Resources\RideResource;
use App\Models\Driver;
use App\Services\DriverMatchingService;
use App\Services\RideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function __construct(
        private readonly RideService $rides,
        private readonly DriverMatchingService $matching,
    ) {}

    /** Open ride requests this driver is eligible to accept, nearest pickup first. */
    public function availableRides(Request $request): JsonResponse
    {
        /** @var Driver $driver */
        $driver = $request->user()->refresh();

        return response()->json([
            'data' => OpenRideResource::collection($this->matching->openRidesFor($driver))->resolve($request),
            'meta' => [
                'availability_status' => $driver->availability_status->value,
                'radius_km' => (float) config('rides.matching.radius_km'),
            ],
        ]);
    }

    /** The driver's ongoing ride, or null. */
    public function currentRide(Request $request): JsonResponse
    {
        $assignment = $request->user()->activeAssignment()->with('ride.rider', 'ride.currentAssignment.driver')->first();

        return response()->json([
            'data' => $assignment ? (new RideResource($assignment->ride))->resolve($request) : null,
        ]);
    }

    public function updateAvailability(UpdateDriverAvailabilityRequest $request): DriverResource
    {
        return new DriverResource($this->rides->setDriverAvailability($request->user(), $request->availability()));
    }
}
