<?php

namespace database\seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed the Super Admin user (password "password", the default set by UserFactory).
     */
    public function run(): void
    {
        User::factory()->create([
            'role' => Role::SuperAdmin,
            'email' => 'admin@email.com',
        ]);
    }
}
