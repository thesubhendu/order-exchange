<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Asset;
use App\Models\Order;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
      
        // Buyer user with USD balance to purchase assets
        $buyer = User::factory()->create([
            'name' => 'Test Buyer',
            'email' => 'buyer@test.test',
            'password' => Hash::make('password'),
            'balance' => 100000.00, // $100,000 USD balance for purchasing
        ]);

        // Seller user with assets to sell
        $seller = User::factory()->create([
            'name' => 'Test Seller',
            'email' => 'seller@test.test',
            'password' => Hash::make('password'),
            'balance' => 50000.00, // $50,000 USD balance (optional, for receiving payment)
        ]);

        // Create BTC asset for seller
        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => 10.00000000, // 10 BTC available to sell
            'locked_amount' => 0.00000000,
        ]);

        // Create ETH asset for seller
        Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'ETH',
            'amount' => 100.00000000, // 100 ETH available to sell
            'locked_amount' => 0.00000000,
        ]);
    }
}
