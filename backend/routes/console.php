<?php

use App\Models\IdempotencyKey;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Forget idempotency keys after their retention window (see IdempotencyKey::RETENTION_HOURS).
Schedule::command('model:prune', ['--model' => [IdempotencyKey::class]])->daily();
