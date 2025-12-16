<?php

use App\Models\User;
use App\Models\Order;
use App\Models\Asset;
use App\Enums\OrderStatus;
use App\Enums\OrderSide;
use App\Enums\Symbol;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->update(['balance' => 10000]);
    $this->user->refresh();
    $this->token = $this->user->createToken('test-token')->plainTextToken;
});

test('unauthenticated user cannot access orders index', function () {
    $response = $this->getJson('/api/orders');
    
    $response->assertStatus(401);
});

test('authenticated user can list open orders', function () {
    // Create some orders
    Order::factory()->create([
        'user_id' => $this->user->id,
        'symbol' => Symbol::BTC->value,
        'side' => OrderSide::BUY->value,
        'status' => OrderStatus::OPEN,
    ]);
    
    Order::factory()->create([
        'user_id' => $this->user->id,
        'symbol' => Symbol::ETH->value,
        'side' => OrderSide::SELL->value,
        'status' => OrderStatus::OPEN,
    ]);
    
    // Create a filled order (should not appear)
    Order::factory()->create([
        'user_id' => $this->user->id,
        'symbol' => Symbol::BTC->value,
        'side' => OrderSide::BUY->value,
        'status' => OrderStatus::FILLED,
    ]);
    
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->getJson('/api/orders');
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'orders',
            'message',
        ])
        ->assertJsonCount(2, 'orders');
});

test('authenticated user can filter orders by symbol', function () {
    Order::factory()->create([
        'user_id' => $this->user->id,
        'symbol' => Symbol::BTC->value,
        'status' => OrderStatus::OPEN,
    ]);
    
    Order::factory()->create([
        'user_id' => $this->user->id,
        'symbol' => Symbol::ETH->value,
        'status' => OrderStatus::OPEN,
    ]);
    
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->getJson('/api/orders?symbol=BTC');
    
    $response->assertStatus(200)
        ->assertJsonCount(1, 'orders')
        ->assertJsonPath('orders.0.symbol', Symbol::BTC->value);
});

test('unauthenticated user cannot create order', function () {
    $response = $this->postJson('/api/orders', [
        'symbol' => 'BTC',
        'side' => 'buy',
        'price' => 10000,
        'amount' => 1,
    ]);
    
    $response->assertStatus(401);
});

test('authenticated user can create buy order', function () {
    $initialBalance = $this->user->balance;
    
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 1000,
            'amount' => 1,
        ]);
    
    $response->assertStatus(201)
        ->assertJsonStructure([
            'order' => [
                'id',
                'user_id',
                'symbol',
                'side',
                'price',
                'amount',
                'status',
            ],
            'message',
        ])
        ->assertJsonPath('order.side', OrderSide::BUY->value)
        ->assertJsonPath('order.symbol', Symbol::BTC->value)
        ->assertJsonPath('order.status', OrderStatus::OPEN->value);
    
    $this->user->refresh();
    $this->assertEqualsWithDelta($initialBalance - 1000, (float) $this->user->balance, 0.01);
});

test('authenticated user can create sell order', function () {
    $asset = Asset::factory()->for($this->user)->create([
        'symbol' => Symbol::BTC->value,
        'amount' => 10,
        'locked_amount' => 0,
    ]);
    
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 1000,
            'amount' => 1,
        ]);
    
    $response->assertStatus(201)
        ->assertJsonPath('order.side', OrderSide::SELL->value)
        ->assertJsonPath('order.status', OrderStatus::OPEN->value);
    
    $asset->refresh();
    $this->assertEqualsWithDelta(9, (float) $asset->amount, 0.00000001);
    $this->assertEqualsWithDelta(1, (float) $asset->locked_amount, 0.00000001);
});

test('order creation validates required fields', function () {
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->postJson('/api/orders', []);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['symbol', 'side', 'price', 'amount']);
});

test('order creation validates symbol enum', function () {
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->postJson('/api/orders', [
            'symbol' => 'INVALID',
            'side' => 'buy',
            'price' => 1000,
            'amount' => 1,
        ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['symbol']);
});

test('order creation validates side enum', function () {
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'invalid',
            'price' => 1000,
            'amount' => 1,
        ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['side']);
});

test('order creation validates price is numeric and positive', function () {
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => -100,
            'amount' => 1,
        ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['price']);
});

test('order creation validates amount is numeric and positive', function () {
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 1000,
            'amount' => -1,
        ]);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('order creation fails with insufficient balance for buy order', function () {
    $this->user->update(['balance' => 100]);
    
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 10000,
            'amount' => 1,
        ]);
    
    $response->assertStatus(422)
        ->assertJsonPath('error', 'Insufficient balance');
});

test('order creation fails with insufficient assets for sell order', function () {
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 1000,
            'amount' => 1,
        ]);
    
    $response->assertStatus(422)
        ->assertJsonPath('error', 'Asset not found');
});

test('unauthenticated user cannot cancel order', function () {
    $order = Order::factory()->create(['user_id' => $this->user->id]);
    
    $response = $this->postJson("/api/orders/{$order->id}/cancel");
    
    $response->assertStatus(401);
});

test('authenticated user can cancel their own buy order', function () {
    $initialBalance = $this->user->balance;
    
    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'side' => OrderSide::BUY->value,
        'price' => 1000,
        'amount' => 1,
        'status' => OrderStatus::OPEN,
    ]);
    
    // Simulate balance deduction
    $this->user->update(['balance' => $initialBalance - 1000]);
    
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->postJson("/api/orders/{$order->id}/cancel");
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'order',
            'message',
        ])
        ->assertJsonPath('order.status', OrderStatus::CANCELLED->value);
    
    $this->user->refresh();
    $this->assertEqualsWithDelta($initialBalance, (float) $this->user->balance, 0.01);
});

test('authenticated user can cancel their own sell order', function () {
    $asset = Asset::factory()->for($this->user)->create([
        'symbol' => Symbol::BTC->value,
        'amount' => 5,
        'locked_amount' => 1,
    ]);
    
    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'side' => OrderSide::SELL->value,
        'symbol' => Symbol::BTC->value,
        'amount' => 1,
        'status' => OrderStatus::OPEN,
    ]);
    
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->postJson("/api/orders/{$order->id}/cancel");
    
    $response->assertStatus(200)
        ->assertJsonPath('order.status', OrderStatus::CANCELLED->value);
    
    $asset->refresh();
    $this->assertEqualsWithDelta(6, (float) $asset->amount, 0.00000001);
    $this->assertEqualsWithDelta(0, (float) $asset->locked_amount, 0.00000001);
});

test('user cannot cancel order that does not belong to them', function () {
    $otherUser = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $otherUser->id,
        'status' => OrderStatus::OPEN,
    ]);
    
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->postJson("/api/orders/{$order->id}/cancel");
    
    $response->assertStatus(403)
        ->assertJsonPath('error', 'You can only cancel your own orders');
});

test('user cannot cancel non-existent order', function () {
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->postJson('/api/orders/non-existent-id/cancel');
    
    $response->assertStatus(404)
        ->assertJsonPath('error', 'The requested order does not exist');
});

test('user cannot cancel already filled order', function () {
    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'status' => OrderStatus::FILLED,
    ]);
    
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->postJson("/api/orders/{$order->id}/cancel");
    
    $response->assertStatus(422)
        ->assertJsonPath('error', 'Order cannot be cancelled');
});

test('user cannot cancel already cancelled order', function () {
    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'status' => OrderStatus::CANCELLED,
    ]);
    
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->postJson("/api/orders/{$order->id}/cancel");
    
    $response->assertStatus(422)
        ->assertJsonPath('error', 'Order cannot be cancelled');
});

test('buy order can be fulfilled when matching sell order exists', function () {
    $seller = User::factory()->create(['balance' => 100]);
    $sellerAsset = Asset::factory()->for($seller)->create([
        'symbol' => Symbol::BTC->value,
        'amount' => 10,
        'locked_amount' => 0,
    ]);
    
    // Create a buyer with sufficient balance
    $buyer = User::factory()->create();
    $buyer->balance = 10000;
    $buyer->save();
    
    // Create sell order via API to properly lock the asset
    $sellResponse = $this->actingAs($seller, 'sanctum')
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 100,
            'amount' => 2,
        ]);
    
    $sellResponse->assertStatus(201);
    
    // Create buy order that matches - use actingAs to ensure correct authentication
    $response = $this->actingAs($buyer, 'sanctum')
        ->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 100,
            'amount' => 2,
        ]);
    
    
    $response->assertStatus(201)
        ->assertJsonPath('order.status', OrderStatus::FILLED->value);
    
    $sellerAsset->refresh();
    $this->assertEqualsWithDelta(8, (float) $sellerAsset->amount, 0.00000001);
    $this->assertEqualsWithDelta(0, (float) $sellerAsset->locked_amount, 0.00000001);
});

test('profile endpoint returns authenticated user', function () {
    $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
        ->getJson('/api/profile');
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'user' => [
                'id',
                'name',
                'email',
            ],
        ])
        ->assertJsonPath('user.id', $this->user->id);
});
