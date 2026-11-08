<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1 Admins
        $admin = User::firstOrCreate([
            'email' => 'admin@gmail.com'
        ], [
            'name' => 'Admin Web',
            'password' => Hash::make('password'),
            'email_verified_at' => now()
        ]);
        $admin->assignRole('Admin');

        // 1 Owner
        $owner = User::firstOrCreate([
            'email' => 'owner@owner.com'
        ], [
            'name' => 'Owner System',
            'password' => Hash::make('password'),
            'email_verified_at' => now()     
        ]);
        $owner->assignRole('Owner');

        // 1 Seeker
        $seeker = User::firstOrCreate([
            'email' => 'seeker@seeker.com'
        ], [
            'name' => 'Seeker General',
            'password' => Hash::make('password'),
            'email_verified_at' => now()
        ]);
        $seeker->assignRole('Seeker');
    }
}
