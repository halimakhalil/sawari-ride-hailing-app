<?php

/*
|--------------------------------------------------------------------------
| Ride hailing domain configuration
|--------------------------------------------------------------------------
|
| Pricing and matching knobs are environment driven so they can be tuned
| per deployment without a code change. Production pricing would come from
| a pricing service (routing engine distance, traffic, surge, promotions).
|
*/

return [

    'currency' => env('RIDES_CURRENCY', 'PKR'),

    'fare' => [
        'base' => (float) env('RIDES_BASE_FARE', 120),
        'per_km' => (float) env('RIDES_PER_KM_RATE', 45),
        'minimum' => (float) env('RIDES_MINIMUM_FARE', 200),
        'multipliers' => [
            'economy' => (float) env('RIDES_ECONOMY_MULTIPLIER', 1.0),
            'comfort' => (float) env('RIDES_COMFORT_MULTIPLIER', 1.45),
        ],
        // Straight-line (Haversine) distance under-estimates real road distance.
        'road_distance_factor' => (float) env('RIDES_ROAD_DISTANCE_FACTOR', 1.3),
    ],

    'matching' => [
        // Only drivers within this radius of the pickup are considered nearby.
        'radius_km' => (float) env('RIDES_MATCHING_RADIUS_KM', 8),
        'average_city_speed_kmh' => (float) env('RIDES_AVERAGE_CITY_SPEED_KMH', 22),
        // Fixed overhead (finding the rider, parking) added to every pickup ETA.
        'pickup_buffer_minutes' => 2,
    ],

    // Service area: rides must start and end inside this Lahore bounding box.
    'service_area' => [
        'name' => 'Lahore',
        'min_latitude' => 31.30,
        'max_latitude' => 31.70,
        'min_longitude' => 74.10,
        'max_longitude' => 74.55,
    ],

    // Known places used by the demo frontend in place of a geocoding service.
    'locations' => [
        ['id' => 'dha-phase-5', 'name' => 'DHA Phase 5', 'area' => 'Defence', 'latitude' => 31.4626, 'longitude' => 74.4085],
        ['id' => 'dha-phase-6', 'name' => 'DHA Phase 6', 'area' => 'Defence', 'latitude' => 31.4733, 'longitude' => 74.4520],
        ['id' => 'gulberg-iii', 'name' => 'Gulberg III', 'area' => 'Gulberg', 'latitude' => 31.5120, 'longitude' => 74.3440],
        ['id' => 'liberty-market', 'name' => 'Liberty Market', 'area' => 'Gulberg', 'latitude' => 31.5104, 'longitude' => 74.3446],
        ['id' => 'mm-alam-road', 'name' => 'MM Alam Road', 'area' => 'Gulberg', 'latitude' => 31.5160, 'longitude' => 74.3510],
        ['id' => 'johar-town', 'name' => 'Johar Town', 'area' => 'Johar Town', 'latitude' => 31.4697, 'longitude' => 74.2728],
        ['id' => 'emporium-mall', 'name' => 'Emporium Mall', 'area' => 'Johar Town', 'latitude' => 31.4672, 'longitude' => 74.2660],
        ['id' => 'model-town', 'name' => 'Model Town', 'area' => 'Model Town', 'latitude' => 31.4830, 'longitude' => 74.3240],
        ['id' => 'mall-road', 'name' => 'Mall Road', 'area' => 'Downtown', 'latitude' => 31.5580, 'longitude' => 74.3280],
        ['id' => 'badshahi-mosque', 'name' => 'Badshahi Mosque', 'area' => 'Walled City', 'latitude' => 31.5880, 'longitude' => 74.3100],
        ['id' => 'allama-iqbal-airport', 'name' => 'Allama Iqbal International Airport', 'area' => 'Airport', 'latitude' => 31.5216, 'longitude' => 74.4036],
        ['id' => 'packages-mall', 'name' => 'Packages Mall', 'area' => 'Walton', 'latitude' => 31.4710, 'longitude' => 74.3560],
    ],

];
