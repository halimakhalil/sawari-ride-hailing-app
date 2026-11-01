<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('riders')->restrictOnDelete();
            $table->string('category', 20);
            $table->string('status', 20)->default('requested');

            $table->string('pickup_address', 255);
            $table->decimal('pickup_latitude', 10, 7);
            $table->decimal('pickup_longitude', 10, 7);
            $table->string('destination_address', 255);
            $table->decimal('destination_latitude', 10, 7);
            $table->decimal('destination_longitude', 10, 7);

            // Fare snapshot taken when the ride is requested; never trusted from the client.
            $table->decimal('distance_km', 8, 2);
            $table->char('currency', 3);
            $table->decimal('fare_multiplier', 4, 2);
            $table->decimal('estimated_fare', 10, 2);
            $table->decimal('final_fare', 10, 2)->nullable();
            $table->unsignedSmallInteger('estimated_pickup_minutes')->nullable();

            $table->timestamp('requested_at')->useCurrent(); // explicit default stops MariaDB adding ON UPDATE
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamps();

            // Equals rider_id while the ride is active and NULL otherwise. A UNIQUE index
            // over it lets the database guarantee one active ride per rider.
            $table->unsignedBigInteger('active_rider_id')->nullable()
                ->storedAs("CASE WHEN status IN ('requested', 'accepted', 'in_progress') THEN rider_id END");
            $table->unique('active_rider_id', 'rides_one_active_ride_per_rider');

            $table->index(['rider_id', 'created_at']);
            // Driver feed: open requests of a category, oldest first.
            $table->index(['status', 'category', 'requested_at']);
        });

        DB::statement("ALTER TABLE rides ADD CONSTRAINT rides_status_check CHECK (status IN ('requested', 'accepted', 'in_progress', 'completed', 'cancelled'))");
        DB::statement("ALTER TABLE rides ADD CONSTRAINT rides_category_check CHECK (category IN ('economy', 'comfort'))");
        DB::statement('ALTER TABLE rides ADD CONSTRAINT rides_estimated_fare_check CHECK (estimated_fare >= 0)');
        DB::statement('ALTER TABLE rides ADD CONSTRAINT rides_final_fare_check CHECK (final_fare IS NULL OR final_fare >= 0)');
        DB::statement('ALTER TABLE rides ADD CONSTRAINT rides_distance_check CHECK (distance_km >= 0)');
        DB::statement('ALTER TABLE rides ADD CONSTRAINT rides_multiplier_check CHECK (fare_multiplier > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('rides');
    }
};
