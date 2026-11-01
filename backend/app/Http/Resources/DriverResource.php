<?php

namespace App\Http\Resources;

use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full driver view, returned to the driver themselves.
 *
 * @mixin Driver
 */
class DriverResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...$this->publicProfile(),
            'email' => $this->email,
            'availability_status' => $this->availability_status->value,
            'current_location' => $this->hasLocation() ? [
                'latitude' => $this->current_latitude,
                'longitude' => $this->current_longitude,
            ] : null,
        ];
    }

    /**
     * What a rider is allowed to see about their assigned driver.
     *
     * @return array<string, mixed>
     */
    public function publicProfile(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'vehicle' => [
                'category' => $this->vehicle_category->value,
                'model' => $this->vehicle_model,
                'plate' => $this->vehicle_plate,
            ],
        ];
    }
}
