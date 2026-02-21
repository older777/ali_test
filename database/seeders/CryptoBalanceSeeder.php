<?php

namespace Database\Seeders;

use App\Models\CryptoBalance;
use App\Models\User;
use Illuminate\Database\Seeder;

class CryptoBalanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all users
        $users = User::all();

        if ($users->isEmpty()) {
            // Create some users if none exist
            $users = User::factory()->count(5)->create();
        }

        // Create crypto balances for each user
        foreach ($users as $user) {
            // Create BTC balance
            CryptoBalance::factory()
                ->for($user)
                ->btc()
                ->create();

            // Create ETH balance
            CryptoBalance::factory()
                ->for($user)
                ->eth()
                ->create();

            // Create USDT balance
            CryptoBalance::factory()
                ->for($user)
                ->usdt()
                ->create();
        }
    }
}
