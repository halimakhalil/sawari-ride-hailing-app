<?php

namespace App\Http\Controllers\Api;

use App\Enums\RideCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\FareEstimateRequest;
use App\Services\DriverMatchingService;
use App\Services\FareCalculator;
use App\Support\GeoPoint;
use Illuminate\Http\JsonResponse;

class FareEstimateController extends Controller
{
    public function __construct(
        private readonly FareCalculator $fares,
        private readonly DriverMatchingService $matching,
    ) {}

    /** Quote every category for a route so the rider can compare before booking. */
    public function __invoke(FareEstimateRequest $request): JsonResponse
    {
        $pickup = new GeoPoint((float) $request->validated('pickup.latitude'), (float) $request->validated('pickup.longitude'));
        $destination = new GeoPoint((float) $request->validated('destination.latitude'), (float) $request->validated('destination.longitude'));

        $estimates = array_map(function (RideCategory $category) use ($pickup, $destination) {
            $quote = $this->fares->quote($pickup, $destination, $category);

            return [
                'category' => $category->value,
                'label' => $category->label(),
                'distance_km' => $quote->distanceKm,
                'currency' => $quote->currency,
                'estimated_fare' => number_format($quote->amount, 2, '.', ''),
                'estimated_pickup_minutes' => $this->matching->estimatePickupMinutes($pickup, $category),
            ];
        }, RideCategory::cases());

        return response()->json(['data' => $estimates]);
    }
}
