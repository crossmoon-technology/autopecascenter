<?php

namespace database\seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed users for each role (password "password", the default set by UserFactory):
     * a single Super Admin, and 10 users each for Seller (already approved) and Client.
     */
    public function run(): void
    {
        User::factory()->create([
            'role' => Role::SuperAdmin,
            'email' => 'admin@email.com',
        ]);

        User::factory()->approvedSeller()->count(10)->create();

        User::factory()->count(10)->create([
            'role' => Role::Client,
        ]);
    }
}
