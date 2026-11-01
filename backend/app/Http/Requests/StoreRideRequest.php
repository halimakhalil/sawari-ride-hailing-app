<?php

namespace App\Http\Requests;

use App\Data\RideRequestData;
use App\Enums\RideCategory;
use Illuminate\Validation\Rule;

class StoreRideRequest extends RideRouteRequest
{
    public const IDEMPOTENCY_HEADER = 'Idempotency-Key';

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->routeRules(),
            'category' => ['required', Rule::enum(RideCategory::class)],
            // Validated with the body so a malformed key yields a normal 422 response.
            'idempotency_key' => ['nullable', 'string', 'min:8', 'max:255', 'regex:/^[A-Za-z0-9_\-:.]+$/'],
        ];
    }

    /** @return array<string, mixed> */
    public function validationData(): array
    {
        return [...$this->all(), 'idempotency_key' => $this->header(self::IDEMPOTENCY_HEADER)];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['idempotency_key' => self::IDEMPOTENCY_HEADER.' header'];
    }

    public function toData(): RideRequestData
    {
        return RideRequestData::fromArray($this->validated());
    }

    public function idempotencyKey(): ?string
    {
        return $this->validated('idempotency_key');
    }
}
