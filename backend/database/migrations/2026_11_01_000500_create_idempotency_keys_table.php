<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('riders')->cascadeOnDelete();
            // Binary collation: keys are case-sensitive opaque tokens.
            $table->string('idempotency_key', 255)->charset('ascii')->collation('ascii_bin');
            // SHA-256 of the normalised request body, to detect key reuse with a different payload.
            $table->char('request_hash', 64);
            $table->foreignId('ride_id')->nullable()->constrained('rides')->restrictOnDelete();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->timestamps();

            // Keys are scoped per rider: two riders may use the same key independently.
            $table->unique(['rider_id', 'idempotency_key'], 'idempotency_keys_rider_key_unique');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
