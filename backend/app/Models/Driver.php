<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\DriverAvailability;
use App\Enums\RideCategory;
use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Driver extends Authenticatable
{
    /** @use HasFactory<DriverFactory> */
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'name', 'email', 'phone', 'password',
        'vehicle_category', 'vehicle_model', 'vehicle_plate',
        'availability_status', 'current_latitude', 'current_longitude', 'location_updated_at',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'vehicle_category' => RideCategory::class,
            'availability_status' => DriverAvailability::class,
            'current_latitude' => 'float',
            'current_longitude' => 'float',
            'location_updated_at' => 'datetime',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DriverAssignment::class);
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(DriverAssignment::class)->where('status', AssignmentStatus::Active);
    }

    public function isAvailable(): bool
    {
        return $this->availability_status === DriverAvailability::Available;
    }

    public function hasLocation(): bool
    {
        return $this->current_latitude !== null && $this->current_longitude !== null;
    }
}
