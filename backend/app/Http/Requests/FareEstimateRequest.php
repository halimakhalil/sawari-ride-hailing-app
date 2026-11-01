<?php

namespace App\Http\Requests;

class FareEstimateRequest extends RideRouteRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->routeRules();
    }
}
