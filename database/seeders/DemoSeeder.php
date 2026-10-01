<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Demo institution + verified admin for local development.
     * Credentials: admin@custograde.test / password123
     */
    public function run(): void
    {
        $institution = Institution::firstOrCreate(
            ['email' => 'demo@custograde.test'],
            [
                'name' => 'Demo Secondary School',
                'type' => 'Secondary School',
                'phone' => '+256700000000',
                'status' => 'active',
            ]
        );

        $user = User::firstOrCreate(
            ['email' => 'admin@custograde.test'],
            [
                'name' => 'Demo Admin',
                'password' => 'password123',
                'institution_id' => $institution->id,
                'role' => 'institution_admin',
                'phone' => '+256700000000',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $institution->update(['owner_id' => $user->id]);
    }
}
