<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Demo institution + verified admin for local development, plus a
     * personal (institution-less) teacher account.
     * Credentials: admin@custograde.test / password123
     *             teacher@custograde.test / password123
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
                'account_type' => User::ACCOUNT_TYPE_INSTITUTIONAL,
                'role' => User::ROLE_FOR_ACCOUNT_TYPE[User::ACCOUNT_TYPE_INSTITUTIONAL],
                'phone' => '+256700000000',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $institution->update(['owner_id' => $user->id]);

        User::firstOrCreate(
            ['email' => 'teacher@custograde.test'],
            [
                'name' => 'Demo Personal Teacher',
                'password' => 'password123',
                'institution_id' => null,
                'account_type' => User::ACCOUNT_TYPE_PERSONAL,
                'role' => User::ROLE_FOR_ACCOUNT_TYPE[User::ACCOUNT_TYPE_PERSONAL],
                'phone' => '+256700000001',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
