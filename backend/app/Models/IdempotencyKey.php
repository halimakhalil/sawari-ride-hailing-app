<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyKey extends Model
{
    use MassPrunable;

    /** How long a key is remembered; retries after this window create a new ride. */
    public const RETENTION_HOURS = 24;

    protected $fillable = ['rider_id', 'idempotency_key', 'request_hash', 'ride_id', 'response_status'];

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    /** @return Builder<IdempotencyKey> */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subHours(self::RETENTION_HOURS));
    }
}
