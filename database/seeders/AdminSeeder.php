<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            [
                'email' => 'bittigerverse@gmail.com',
            ],
            [
                'first_name' => 'Muhsin',
                'last_name' => 'Gambo',

                'bybit_api_key' => env('BYBIT_API_KEY'),
                'bybit_api_secret' => env('BYBIT_API_SECRET'),

                'api_url' => env('API_URL'),

                'password' => Hash::make('0249+!.a5s56779'), // 🔐 change later

                'status' => 'active',
                'role' => 'admin',
            ]
        );
    }
}