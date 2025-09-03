<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->insert([
            'name' => 'Super Admin',
            'email' => 'admin@dental.com',
            'password' => Hash::make('password123'), // Always hash passwords
            'role' => 'super_admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
