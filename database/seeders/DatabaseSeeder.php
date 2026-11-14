<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach (config('sample_fleet') as $vehicleData) {
            Vehicle::query()->firstOrCreate(
                ['id' => $vehicleData['id']],
                [
                    'name' => $vehicleData['name'],
                    'brand' => $vehicleData['brand'] ?? 'FleetGuard',
                    'model' => $vehicleData['model'] ?? $vehicleData['name'],
                    'category' => $vehicleData['category'],
                    'description' => $vehicleData['description'] ?? 'Sample FleetGuard vehicle.',
                    'daily_rate' => $vehicleData['daily_rate'],
                    'passenger_capacity' => $vehicleData['passengers'] ? (int) filter_var($vehicleData['passengers'], FILTER_SANITIZE_NUMBER_INT) : 5,
                    'transmission' => $vehicleData['transmission'],
                    'fuel_type' => $vehicleData['fuel'],
                    'image_path' => $vehicleData['image'],
                    'status' => 'active',
                ],
            );
        }

        $adminEmail = env('APP_ADMIN_EMAIL');
        $adminPassword = env('APP_ADMIN_PASSWORD');

        if ($adminEmail && $adminPassword) {
            $admin = User::query()->firstOrNew(['email' => $adminEmail]);
            $admin->forceFill([
                'name' => $admin->exists ? $admin->name : 'FleetGuard Administrator',
                'password' => $adminPassword,
                'role' => 'admin',
            ])->save();
        }
    }
}
