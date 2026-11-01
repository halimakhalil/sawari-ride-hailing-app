<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email')->unique();
            $table->string('phone', 20)->unique();
            $table->string('password');
            $table->string('vehicle_category', 20);
            $table->string('vehicle_model', 80);
            $table->string('vehicle_plate', 20)->unique();
            // available = online and idle, busy = on a ride, offline = not taking rides
            $table->string('availability_status', 20)->default('offline');
            $table->decimal('current_latitude', 10, 7)->nullable();
            $table->decimal('current_longitude', 10, 7)->nullable();
            $table->timestamp('location_updated_at')->nullable();
            $table->timestamps();

            // Matching looks up idle drivers of a category.
            $table->index(['availability_status', 'vehicle_category']);
        });

        DB::statement("ALTER TABLE drivers ADD CONSTRAINT drivers_availability_status_check CHECK (availability_status IN ('available', 'busy', 'offline'))");
        DB::statement("ALTER TABLE drivers ADD CONSTRAINT drivers_vehicle_category_check CHECK (vehicle_category IN ('economy', 'comfort'))");
        DB::statement('ALTER TABLE drivers ADD CONSTRAINT drivers_latitude_check CHECK (current_latitude IS NULL OR current_latitude BETWEEN -90 AND 90)');
        DB::statement('ALTER TABLE drivers ADD CONSTRAINT drivers_longitude_check CHECK (current_longitude IS NULL OR current_longitude BETWEEN -180 AND 180)');
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
