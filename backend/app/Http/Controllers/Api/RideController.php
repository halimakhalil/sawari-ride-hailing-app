<?php

namespace App\Http\Controllers\Api;

use App\Enums\RideStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelRideRequest;
use App\Http\Requests\StoreRideRequest;
use App\Http\Resources\RideResource;
use App\Models\Ride;
use App\Services\RideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class RideController extends Controller
{
    private const RELATIONS = ['rider', 'currentAssignment.driver'];

    public function __construct(private readonly RideService $rides) {}

    /** Rider's own rides, newest first. `?status=active` returns only the ongoing ride. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', 'in:active']]);

        $rides = $request->user()->rides()
            ->when($request->query('status') === 'active', fn ($query) => $query->whereIn('status', RideStatus::active()))
            ->with(self::RELATIONS)
            ->latest('id')
            ->paginate(10);

        return RideResource::collection($rides);
    }

    public function store(StoreRideRequest $request): JsonResponse
    {
        $result = $this->rides->requestRide($request->user(), $request->toData(), $request->idempotencyKey());

        return (new RideResource($result->ride->load(self::RELATIONS)))
            ->response()
            // A replay returns the original ride: 201 the first time, 200 for retries.
            ->setStatusCode($result->replayed ? 200 : 201)
            ->header('Idempotent-Replayed', $result->replayed ? 'true' : 'false');
    }

    public function show(Ride $ride): RideResource
    {
        Gate::authorize('view', $ride);

        return new RideResource($ride->load(self::RELATIONS));
    }

    public function cancel(CancelRideRequest $request, Ride $ride): RideResource
    {
        $ride = $this->rides->cancel($ride->id, $request->user(), $request->validated('reason'));

        return new RideResource($ride->load(self::RELATIONS));
    }

    public function accept(Request $request, Ride $ride): RideResource
    {
        return new RideResource($this->rides->accept($ride->id, $request->user())->load(self::RELATIONS));
    }

    public function start(Request $request, Ride $ride): RideResource
    {
        return new RideResource($this->rides->start($ride->id, $request->user())->load(self::RELATIONS));
    }

    public function complete(Request $request, Ride $ride): RideResource
    {
        return new RideResource($this->rides->complete($ride->id, $request->user())->load(self::RELATIONS));
    }
}
