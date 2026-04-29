<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Super admin (no company)
        User::create([
            'name'     => 'Super Admin',
            'email'    => 'admin@stalwart.com',
            'password' => Hash::make('password'),
            'role'     => 'super_admin',
            'is_active' => true,
        ]);

        // Stalwart's own tenant account
        $company = Company::create([
            'name'              => 'Stalwart Zambia',
            'slug'              => 'stalwart',
            'email'             => 'info@stalwart.com',
            'phone'             => '+260971000000',
            'country'           => 'Zambia',
            'status'            => 'active',
            'subscription_plan' => 'enterprise',
        ]);

        // Stalwart admin user
        User::create([
            'company_id' => $company->id,
            'name'       => 'Stalwart Admin',
            'email'      => 'admin@stalwart-lms.com',
            'password'   => Hash::make('password'),
            'role'       => 'admin',
            'is_active'  => true,
        ]);
    }
}
