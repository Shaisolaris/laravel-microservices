<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo admin user
        \App\Models\User::factory()->create([
            'name' => 'Sarah Chen',
            'email' => 'admin@demo.com',
            'password' => Hash::make('demo123'),
        ]);

        // Demo team members
        \App\Models\User::factory(4)->create();

        $this->command->info('🌱 Seeded 5 demo users (admin@demo.com / demo123)');
    }
}
