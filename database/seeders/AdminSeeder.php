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

                'bybit_api_key' => 'pSZzmfSlToyG3U0pEq',
                'bybit_api_secret' => '2Y1w1TUeJiynpSBifXglOlpMZktjkqEOcufD',

                'password' => Hash::make('0249+!.a5s56779'), // 🔐 change later

                'status' => 'active',
                'role' => 'admin',
            ]
        );
    }
}