<?php

namespace App\Services;

use App\Enums\RideCategory;

final readonly class FareQuote
{
    public function __construct(
        public RideCategory $category,
        public float $distanceKm,
        public float $multiplier,
        public float $amount,
        public string $currency,
    ) {}
}
