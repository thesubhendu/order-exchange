<?php

use App\Models\User;
use App\Enums\OrderStatus;
use App\Services\OrderService;
use App\DTOs\CreateLimitOrderDTO;

beforeEach(function () {
    $this->orderService = resolve(OrderService::class);
});


test('can create buy order', function () {
    $buyerInitialBalance = 10000;
    $buyer = User::factory()->create(['balance' => 10000]);


    $order = $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 10000,
            'amount' => 1,
        ])
    );

    $sellingPrice = 10000*1;

    $this->assertEquals(OrderStatus::OPEN, $order->status);
    $this->assertEquals($buyer->fresh()->balance, $buyerInitialBalance-$sellingPrice);
});

test('can create sell order', function () {
    $sellerInitialBalance = 100;
    $seller = User::factory()->create(['balance' => $sellerInitialBalance]);

    // seller
    $sellingAsset = \App\Models\Asset::factory()
        ->for($seller)
        ->create([
            'symbol' => 'BTC',
            'amount' => 1000,
            'locked_amount' => 0
        ]);

    $order = $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($seller, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 10000,
            'amount' => 1,
        ])
    );
    $this->assertEquals($sellingAsset->fresh()->locked_amount, 1);
    $this->assertEquals(OrderStatus::OPEN, $order->status);
});


test('cannot order if insufficient balance', function () {
    $user = User::factory()->create(['balance' => 100]);

    $this->expectException(Exception::class);

    $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($user, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 10000,
            'amount' => 1,
        ])
    );
});


// CANCEL TEST

test('can cancel buy order', function () {
    $buyerInitialBalance = 10000;
    $buyer = User::factory()->create(['balance' => 10000]);


    $order = $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 1000,
            'amount' => 1,
        ])
    );

    $sellingPrice = 1000*1;

    $this->assertEquals(OrderStatus::OPEN, $order->status);

    $this->assertEquals($buyer->fresh()->balance, $buyerInitialBalance-$sellingPrice);

    $cancelledOrder = $this->orderService->cancelOrder($order->id);

    $this->assertEquals(OrderStatus::CANCELLED, $cancelledOrder->status);


    $this->assertEquals($buyer->fresh()->balance, $buyerInitialBalance);

});




// FULFILL Test


test('can fulfill order', function () {
    $buyerInitialBalance = 1000;
    $sellerInitialBalance = 100;
    $buyer = User::factory()->create(['balance' => $buyerInitialBalance]);
    $seller = User::factory()->create(['balance' => $sellerInitialBalance]);

    // seller
    $sellingAsset = \App\Models\Asset::factory()
        ->for($seller)
        ->create([
            'symbol' => 'BTC',
            'amount' => 1000,
            'locked_amount' => 0
        ]);

     $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($seller, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 100,
            'amount' => 2,
        ])
    );

    $buyOrder = $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 100,
            'amount' => 2,
        ])
    );

    expect($buyOrder->status)->toBe(OrderStatus::FILLED);
    $this->assertEquals($sellingAsset->fresh()->amount, 998);

    $sellingPrice = 100*2;
    $commission = $sellingPrice*0.015;

    $this->assertEquals($seller->fresh()->balance, $sellerInitialBalance-$commission);
});
