<?php

namespace App\Enums;

enum RideCategory: string
{
    case Economy = 'economy';
    case Comfort = 'comfort';

    public function label(): string
    {
        return match ($this) {
            self::Economy => 'Economy',
            self::Comfort => 'Comfort',
        };
    }

    public function fareMultiplier(): float
    {
        return (float) config("rides.fare.multipliers.{$this->value}");
    }
}
