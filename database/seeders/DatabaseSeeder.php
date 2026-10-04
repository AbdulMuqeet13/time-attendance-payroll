<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed roles and the first Super Admin. Run DemoDataSeeder separately for sample data.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Administrator', 'password' => 'password', 'email_verified_at' => now()],
        )->assignRole(RoleEnum::SuperAdmin->value);
    }
}
