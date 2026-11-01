<?php

namespace App\Data;

use App\Enums\RideCategory;
use App\Support\GeoPoint;

/** Validated input for requesting a ride, decoupled from the HTTP layer. */
final readonly class RideRequestData
{
    public function __construct(
        public string $pickupAddress,
        public GeoPoint $pickup,
        public string $destinationAddress,
        public GeoPoint $destination,
        public RideCategory $category,
    ) {}

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        return new self(
            pickupAddress: $input['pickup']['address'],
            pickup: new GeoPoint((float) $input['pickup']['latitude'], (float) $input['pickup']['longitude']),
            destinationAddress: $input['destination']['address'],
            destination: new GeoPoint((float) $input['destination']['latitude'], (float) $input['destination']['longitude']),
            category: RideCategory::from($input['category']),
        );
    }

    /**
     * Stable hash of the request payload. An idempotency key replayed with a
     * different payload is a client bug and must not return the original ride.
     */
    public function fingerprint(): string
    {
        return hash('sha256', json_encode([
            $this->pickupAddress,
            round($this->pickup->latitude, 7),
            round($this->pickup->longitude, 7),
            $this->destinationAddress,
            round($this->destination->latitude, 7),
            round($this->destination->longitude, 7),
            $this->category->value,
        ], JSON_THROW_ON_ERROR));
    }
}
