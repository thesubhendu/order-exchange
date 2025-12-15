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
        User::factory()
        ->has(Asset::factory()->count(1))
        ->has(Order::factory()->count(1))
        ->create([
            'name' => 'Subu Bhatta',
            'email' => 'subu@test.test',
            'password' => Hash::make('password'),
        ]);

    }
}
