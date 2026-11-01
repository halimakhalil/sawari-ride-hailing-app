<?php

namespace App\Http\Requests;

use App\Support\GeoPoint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Shared validation for a pickup → destination pair inside the service area. */
abstract class RideRouteRequest extends FormRequest
{
    /** Pickup and destination closer than this are treated as the same place. */
    private const MIN_TRIP_KM = 0.3;

    /** @return array<string, mixed> */
    protected function routeRules(): array
    {
        $area = config('rides.service_area');
        $rules = [];

        foreach (['pickup', 'destination'] as $point) {
            $rules[$point] = ['required', 'array:address,latitude,longitude'];
            // Addresses are free text shown to drivers; reject markup outright.
            $rules["{$point}.address"] = ['required', 'string', 'min:2', 'max:255', 'not_regex:/[<>]/'];
            $rules["{$point}.latitude"] = ['required', 'numeric', "between:{$area['min_latitude']},{$area['max_latitude']}"];
            $rules["{$point}.longitude"] = ['required', 'numeric', "between:{$area['min_longitude']},{$area['max_longitude']}"];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $area = config('rides.service_area.name');

        return [
            '*.latitude.between' => "The location must be inside our {$area} service area.",
            '*.longitude.between' => "The location must be inside our {$area} service area.",
            '*.address.not_regex' => 'The address contains invalid characters.',
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $pickup = new GeoPoint((float) $this->input('pickup.latitude'), (float) $this->input('pickup.longitude'));
                $destination = new GeoPoint((float) $this->input('destination.latitude'), (float) $this->input('destination.longitude'));

                if ($pickup->distanceTo($destination) < self::MIN_TRIP_KM) {
                    $validator->errors()->add('destination', 'The destination must be different from the pickup location.');
                }
            },
        ];
    }
}
