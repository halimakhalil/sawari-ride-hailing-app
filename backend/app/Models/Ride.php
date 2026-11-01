<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\RideCategory;
use App\Enums\RideStatus;
use Database\Factories\RideFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ride extends Model
{
    /** @use HasFactory<RideFactory> */
    use HasFactory;

    protected $fillable = [
        'rider_id', 'category', 'status',
        'pickup_address', 'pickup_latitude', 'pickup_longitude',
        'destination_address', 'destination_latitude', 'destination_longitude',
        'distance_km', 'currency', 'fare_multiplier', 'estimated_fare', 'final_fare',
        'estimated_pickup_minutes',
        'requested_at', 'accepted_at', 'started_at', 'completed_at', 'cancelled_at',
        'cancellation_reason',
    ];

    protected $attributes = [
        'status' => 'requested',
    ];

    protected function casts(): array
    {
        return [
            'category' => RideCategory::class,
            'status' => RideStatus::class,
            'pickup_latitude' => 'float',
            'pickup_longitude' => 'float',
            'destination_latitude' => 'float',
            'destination_longitude' => 'float',
            'distance_km' => 'decimal:2',
            'fare_multiplier' => 'decimal:2',
            'estimated_fare' => 'decimal:2',
            'final_fare' => 'decimal:2',
            'estimated_pickup_minutes' => 'integer',
            'requested_at' => 'datetime',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DriverAssignment::class);
    }

    /** The assignment that currently (or most recently) serves this ride. */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(DriverAssignment::class)->latestOfMany();
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(DriverAssignment::class)->where('status', AssignmentStatus::Active);
    }

    /** @param Builder<Ride> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', RideStatus::active());
    }
}
