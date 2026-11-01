<?php

namespace App\Exceptions;

use App\Enums\RideStatus;

class InvalidRideTransitionException extends ApiException
{
    public function __construct(RideStatus $from, RideStatus $to)
    {
        parent::__construct(
            409,
            'INVALID_RIDE_TRANSITION',
            "A ride that is {$from->value} cannot become {$to->value}.",
            [
                'current_status' => $from->value,
                'requested_status' => $to->value,
                'allowed_transitions' => array_map(fn (RideStatus $s) => $s->value, $from->allowedTransitions()),
            ],
        );
    }
}
