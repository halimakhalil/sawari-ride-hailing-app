<?php

namespace Database\Seeders;

use App\Enums\DriverAvailability;
use App\Enums\RideCategory;
use App\Models\Driver;
use App\Models\Rider;
use Illuminate\Database\Seeder;

/**
 * Demo data. Every account uses the password "password" (development only).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $riders = [
            ['name' => 'Ayesha Khan', 'email' => 'rider@example.com', 'phone' => '+923001234567'],
            ['name' => 'Bilal Ahmed', 'email' => 'rider2@example.com', 'phone' => '+923001234568'],
        ];

        foreach ($riders as $rider) {
            Rider::updateOrCreate(['email' => $rider['email']], [...$rider, 'password' => 'password']);
        }

        $drivers = [
            // Available economy drivers spread across Lahore.
            ['name' => 'Usman Tariq', 'email' => 'driver@example.com', 'phone' => '+923211111111', 'vehicle_category' => RideCategory::Economy, 'vehicle_model' => 'Suzuki Alto', 'vehicle_plate' => 'LEA-1234', 'availability_status' => DriverAvailability::Available, 'current_latitude' => 31.5150, 'current_longitude' => 74.3480],
            ['name' => 'Hamza Iqbal', 'email' => 'driver2@example.com', 'phone' => '+923212222222', 'vehicle_category' => RideCategory::Economy, 'vehicle_model' => 'Suzuki Cultus', 'vehicle_plate' => 'LEB-5678', 'availability_status' => DriverAvailability::Available, 'current_latitude' => 31.4700, 'current_longitude' => 74.2800],
            // Available comfort driver near DHA.
            ['name' => 'Sana Malik', 'email' => 'driver3@example.com', 'phone' => '+923213333333', 'vehicle_category' => RideCategory::Comfort, 'vehicle_model' => 'Toyota Corolla', 'vehicle_plate' => 'LEC-9012', 'availability_status' => DriverAvailability::Available, 'current_latitude' => 31.4680, 'current_longitude' => 74.4100],
            // Offline drivers: not matched until they go online.
            ['name' => 'Imran Shah', 'email' => 'driver4@example.com', 'phone' => '+923214444444', 'vehicle_category' => RideCategory::Comfort, 'vehicle_model' => 'Honda Civic', 'vehicle_plate' => 'LED-3456', 'availability_status' => DriverAvailability::Offline, 'current_latitude' => 31.5200, 'current_longitude' => 74.3500],
            ['name' => 'Faisal Raza', 'email' => 'driver5@example.com', 'phone' => '+923215555555', 'vehicle_category' => RideCategory::Economy, 'vehicle_model' => 'Suzuki Wagon R', 'vehicle_plate' => 'LEE-7890', 'availability_status' => DriverAvailability::Offline, 'current_latitude' => 31.4830, 'current_longitude' => 74.3240],
        ];

        foreach ($drivers as $driver) {
            Driver::updateOrCreate(['email' => $driver['email']], [
                ...$driver,
                'password' => 'password',
                'location_updated_at' => now(),
            ]);
        }
    }
}
