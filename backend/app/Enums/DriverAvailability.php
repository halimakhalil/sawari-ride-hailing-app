<?php

namespace App\Enums;

enum DriverAvailability: string
{
    /** Online and idle: may be offered and accept rides. */
    case Available = 'available';
    /** Currently assigned to a ride. Only the system sets this. */
    case Busy = 'busy';
    /** Not taking rides. */
    case Offline = 'offline';
}
