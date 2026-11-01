<?php

namespace App\Http\Requests;

use App\Enums\DriverAvailability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverAvailabilityRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // "busy" is managed by the ride lifecycle, never set by the driver.
            'status' => ['required', Rule::enum(DriverAvailability::class)->only([DriverAvailability::Available, DriverAvailability::Offline])],
        ];
    }

    public function availability(): DriverAvailability
    {
        return DriverAvailability::from($this->validated('status'));
    }
}
