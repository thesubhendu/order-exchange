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
    // Seller receives full sale proceeds (commission is deducted from buyer, not seller)
    $this->assertEquals($seller->fresh()->balance, $sellerInitialBalance + $sellingPrice);
});


test('can cancel sell order', function () {
    $sellerInitialBalance = 100;
    $seller = User::factory()->create(['balance' => $sellerInitialBalance]);

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
            'amount' => 5,
        ])
    );

    $this->assertEquals(OrderStatus::OPEN, $order->status);
    $this->assertEquals($sellingAsset->fresh()->locked_amount, 5);
    $this->assertEquals($sellingAsset->fresh()->amount, 995);

    $cancelledOrder = $this->orderService->cancelOrder($order->id);

    $this->assertEquals(OrderStatus::CANCELLED, $cancelledOrder->status);
    $this->assertEquals($sellingAsset->fresh()->locked_amount, 0);
    $this->assertEquals($sellingAsset->fresh()->amount, 1000);
});


test('cannot sell order if insufficient assets', function () {
    $seller = User::factory()->create(['balance' => 100]);

    $sellingAsset = \App\Models\Asset::factory()
        ->for($seller)
        ->create([
            'symbol' => 'BTC',
            'amount' => 5,
            'locked_amount' => 0
        ]);

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Insufficient assets');

    $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($seller, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 10000,
            'amount' => 10,
        ])
    );
});


test('cannot sell order if asset not found', function () {
    $seller = User::factory()->create(['balance' => 100]);

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Asset not found');

    $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($seller, [
            'symbol' => 'ETH',
            'side' => 'sell',
            'price' => 5000,
            'amount' => 1,
        ])
    );
});


test('cannot cancel already filled order', function () {
    $buyerInitialBalance = 1000;
    $sellerInitialBalance = 100;
    $buyer = User::factory()->create(['balance' => $buyerInitialBalance]);
    $seller = User::factory()->create(['balance' => $sellerInitialBalance]);

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

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Order cannot be cancelled');

    $this->orderService->cancelOrder($buyOrder->id);
});


test('cannot cancel already cancelled order', function () {
    $buyer = User::factory()->create(['balance' => 10000]);

    $order = $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 1000,
            'amount' => 1,
        ])
    );

    $this->assertEquals(OrderStatus::OPEN, $order->status);

    $cancelledOrder = $this->orderService->cancelOrder($order->id);
    $this->assertEquals(OrderStatus::CANCELLED, $cancelledOrder->status);

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Order cannot be cancelled');

    $this->orderService->cancelOrder($cancelledOrder->id);
});


test('commission is charged on buyer', function () {
    $buyerInitialBalance = 1000;
    $sellerInitialBalance = 0;
    $buyer = User::factory()->create(['balance' => $buyerInitialBalance]);
    $seller = User::factory()->create(['balance' => $sellerInitialBalance]);

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

    $executionPrice = 100;
    $executionAmount = 2;
    $totalValue = $executionPrice * $executionAmount;
    $commission = 0.015 * $totalValue; // 1.5% commission

    expect($buyOrder->status)->toBe(OrderStatus::FILLED);
    
    // Buyer pays: execution price + commission
    $expectedBuyerBalance = $buyerInitialBalance - $totalValue - $commission;
    $this->assertEquals($buyer->fresh()->balance, $expectedBuyerBalance);
    
    // Seller receives: execution price (no commission deduction)
    $this->assertEquals($seller->fresh()->balance, $sellerInitialBalance + $totalValue);
});


test('buyer gets refund when execution price is lower than order price', function () {
    $buyerInitialBalance = 1000;
    $buyer = User::factory()->create(['balance' => $buyerInitialBalance]);
    $seller = User::factory()->create(['balance' => 0]);

    $sellingAsset = \App\Models\Asset::factory()
        ->for($seller)
        ->create([
            'symbol' => 'BTC',
            'amount' => 1000,
            'locked_amount' => 0
        ]);

    // Seller creates order at price 80
    $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($seller, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 80,
            'amount' => 2,
        ])
    );

    // Buyer creates order at price 100 (higher than sell order)
    $buyOrder = $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 100,
            'amount' => 2,
        ])
    );

    // Order should execute at 80 (seller's price), buyer gets refund
    $buyerPaid = 100 * 2; // What buyer reserved
    $executionPrice = 80; // Actual execution price
    $executionAmount = 2;
    $totalValue = $executionPrice * $executionAmount;
    $commission = 0.015 * $totalValue; // 1.5% on actual execution
    $buyerShouldPay = $totalValue + $commission;
    $refund = $buyerPaid - $buyerShouldPay;

    expect($buyOrder->status)->toBe(OrderStatus::FILLED);
    
    $expectedBuyerBalance = $buyerInitialBalance - $buyerShouldPay;
    $this->assertEquals($buyer->fresh()->balance, $expectedBuyerBalance);
});


test('price-time priority for buy orders - highest price matched first', function () {
    $seller = User::factory()->create(['balance' => 0]);
    $buyer1 = User::factory()->create(['balance' => 1000]);
    $buyer2 = User::factory()->create(['balance' => 1000]);

    $sellingAsset = \App\Models\Asset::factory()
        ->for($seller)
        ->create([
            'symbol' => 'BTC',
            'amount' => 1000,
            'locked_amount' => 0
        ]);

    // Create two buy orders - lower price first
    $lowPriceBuyOrder = $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($buyer1, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 90,
            'amount' => 2,
        ])
    );

    $highPriceBuyOrder = $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($buyer2, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 110,
            'amount' => 2,
        ])
    );

    // Both should be open
    $this->assertEquals(OrderStatus::OPEN, $lowPriceBuyOrder->status);
    $this->assertEquals(OrderStatus::OPEN, $highPriceBuyOrder->status);

    // Create sell order at price 100
    $sellOrder = $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($seller, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 100,
            'amount' => 2,
        ])
    );

    // High price buy order should be matched (price 110 >= 100)
    $this->assertEquals(OrderStatus::FILLED, $sellOrder->fresh()->status);
    $this->assertEquals(OrderStatus::FILLED, $highPriceBuyOrder->fresh()->status);
    $this->assertEquals(OrderStatus::OPEN, $lowPriceBuyOrder->fresh()->status);
});


test('price-time priority for sell orders - lowest price matched first', function () {
    $buyer = User::factory()->create(['balance' => 1000]);
    $seller1 = User::factory()->create(['balance' => 0]);
    $seller2 = User::factory()->create(['balance' => 0]);

    $sellingAsset1 = \App\Models\Asset::factory()
        ->for($seller1)
        ->create([
            'symbol' => 'BTC',
            'amount' => 1000,
            'locked_amount' => 0
        ]);

    $sellingAsset2 = \App\Models\Asset::factory()
        ->for($seller2)
        ->create([
            'symbol' => 'BTC',
            'amount' => 1000,
            'locked_amount' => 0
        ]);

    // Create two sell orders - higher price first
    $highPriceSellOrder = $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($seller1, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 110,
            'amount' => 2,
        ])
    );

    $lowPriceSellOrder = $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($seller2, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 90,
            'amount' => 2,
        ])
    );

    // Both should be open
    $this->assertEquals(OrderStatus::OPEN, $highPriceSellOrder->status);
    $this->assertEquals(OrderStatus::OPEN, $lowPriceSellOrder->status);

    // Create buy order at price 100
    $buyOrder = $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 100,
            'amount' => 2,
        ])
    );

    // Low price sell order should be matched (price 90 <= 100)
    $this->assertEquals(OrderStatus::FILLED, $buyOrder->status);
    $this->assertEquals(OrderStatus::FILLED, $lowPriceSellOrder->fresh()->status);
    $this->assertEquals(OrderStatus::OPEN, $highPriceSellOrder->fresh()->status);
});


test('buyer asset is created when not exists', function () {
    $buyerInitialBalance = 1000;
    $buyer = User::factory()->create(['balance' => $buyerInitialBalance]);
    $seller = User::factory()->create(['balance' => 0]);

    $sellingAsset = \App\Models\Asset::factory()
        ->for($seller)
        ->create([
            'symbol' => 'BTC',
            'amount' => 1000,
            'locked_amount' => 0
        ]);

    // Verify buyer doesn't have the asset
    $this->assertNull(\App\Models\Asset::query()
        ->where('user_id', $buyer->id)
        ->where('symbol', 'BTC')
        ->first());

    $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($seller, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 100,
            'amount' => 3,
        ])
    );

    $buyOrder = $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 100,
            'amount' => 3,
        ])
    );

    expect($buyOrder->status)->toBe(OrderStatus::FILLED);

    // Verify buyer now has the asset
    $buyerAsset = \App\Models\Asset::query()
        ->where('user_id', $buyer->id)
        ->where('symbol', 'BTC')
        ->first();

    $this->assertNotNull($buyerAsset);
    $this->assertEquals(3, $buyerAsset->amount);
    $this->assertEquals(0, $buyerAsset->locked_amount);
});


test('buyer asset is updated when already exists', function () {
    $buyerInitialBalance = 1000;
    $buyer = User::factory()->create(['balance' => $buyerInitialBalance]);
    $seller = User::factory()->create(['balance' => 0]);

    // Buyer already has 10 BTC
    $buyerAsset = \App\Models\Asset::factory()
        ->for($buyer)
        ->create([
            'symbol' => 'BTC',
            'amount' => 10,
            'locked_amount' => 0
        ]);

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
            'amount' => 3,
        ])
    );

    $buyOrder = $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($buyer, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 100,
            'amount' => 3,
        ])
    );

    expect($buyOrder->status)->toBe(OrderStatus::FILLED);

    // Verify buyer asset is updated correctly (10 + 3 = 13)
    $this->assertEquals(13, $buyerAsset->fresh()->amount);
});


test('get open orders returns all open orders', function () {
    $user1 = User::factory()->create(['balance' => 10000]);
    $user2 = User::factory()->create(['balance' => 10000]);

    $order1 = $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($user1, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 100,
            'amount' => 1,
        ])
    );

    $order2 = $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($user2, [
            'symbol' => 'ETH',
            'side' => 'buy',
            'price' => 50,
            'amount' => 2,
        ])
    );

    $openOrders = $this->orderService->getOpenOrders();

    $this->assertCount(2, $openOrders);
    $this->assertTrue($openOrders->contains('id', $order1->id));
    $this->assertTrue($openOrders->contains('id', $order2->id));
});


test('get open orders filtered by symbol', function () {
    $user1 = User::factory()->create(['balance' => 10000]);
    $user2 = User::factory()->create(['balance' => 10000]);

    $btcOrder = $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($user1, [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 100,
            'amount' => 1,
        ])
    );

    $ethOrder = $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($user2, [
            'symbol' => 'ETH',
            'side' => 'buy',
            'price' => 50,
            'amount' => 2,
        ])
    );

    $btcOpenOrders = $this->orderService->getOpenOrders('BTC');

    $this->assertCount(1, $btcOpenOrders);
    $this->assertTrue($btcOpenOrders->contains('id', $btcOrder->id));
    $this->assertFalse($btcOpenOrders->contains('id', $ethOrder->id));
});


test('asset locked amount respects available balance', function () {
    $seller = User::factory()->create(['balance' => 100]);

    $sellingAsset = \App\Models\Asset::factory()
        ->for($seller)
        ->create([
            'symbol' => 'BTC',
            'amount' => 100,
            'locked_amount' => 60
        ]);

    // Available = 100 - 60 = 40, trying to sell 50 should fail
    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Insufficient assets');

    $this->orderService->createLimitOrder(
        CreateLimitOrderDTO::fromRequest($seller, [
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 1000,
            'amount' => 50,
        ])
    );
});


test('order not found throws exception on cancel', function () {
    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Order not found');

    $this->orderService->cancelOrder('non-existent-id');
});
