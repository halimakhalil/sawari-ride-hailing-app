<?php

namespace App\Models;

use Database\Factories\RiderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Rider extends Authenticatable
{
    /** @use HasFactory<RiderFactory> */
    use HasApiTokens, HasFactory;

    protected $fillable = ['name', 'email', 'phone', 'password'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }
}
