<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user1 = User::updateOrCreate(
            [
                'email' => 'test@aurapay.local',
            ],
            [
                'name' => 'Viktoriya Test',
                'password' => 'DemoSecurePass2026!',
            ]
        );

        Wallet::updateOrCreate(
            [
                'user_id' => $user1->id,
            ],
            [
                'balance' => 2500.00,
                'currency' => 'EUR',
                'status' => 'active',
            ]
        );


        $user2 = User::updateOrCreate(
            [
                'email' => 'receiver@aurapay.local',
            ],
            [
                'name' => 'Demo Receiver',
                'password' => 'DemoReceiver2026!',
            ]
        );

        Wallet::updateOrCreate(
            [
                'user_id' => $user2->id,
            ],
            [
                'balance' => 500.00,
                'currency' => 'EUR',
                'status' => 'active',
            ]
        );
    }
}