<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_id')->constrained('rides')->restrictOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('assigned_at')->useCurrent(); // explicit default stops MariaDB adding ON UPDATE
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            // Last line of defence behind the row locks in RideService: while an
            // assignment is active these columns carry the ride / driver id, so the
            // UNIQUE indexes forbid a second active assignment for either of them.
            // Finished assignments become NULL and are kept as history.
            $table->unsignedBigInteger('active_ride_id')->nullable()
                ->storedAs("CASE WHEN status = 'active' THEN ride_id END");
            $table->unsignedBigInteger('active_driver_id')->nullable()
                ->storedAs("CASE WHEN status = 'active' THEN driver_id END");
            $table->unique('active_ride_id', 'assignments_one_active_per_ride');
            $table->unique('active_driver_id', 'assignments_one_active_per_driver');

            $table->index(['driver_id', 'status']);
        });

        DB::statement("ALTER TABLE driver_assignments ADD CONSTRAINT driver_assignments_status_check CHECK (status IN ('active', 'completed', 'cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_assignments');
    }
};
