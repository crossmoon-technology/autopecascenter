<?php

namespace database\seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed users for each role (password "password", the default set by UserFactory):
     * a single Super Admin, and 10 users each for Admin and Client.
     */
    public function run(): void
    {
        User::factory()->create([
            'role' => Role::SuperAdmin,
            'email' => 'admin@email.com',
        ]);

        foreach ([Role::Admin, Role::Client] as $role) {
            User::factory()->count(10)->create([
                'role' => $role,
            ]);
        }
    }
}
