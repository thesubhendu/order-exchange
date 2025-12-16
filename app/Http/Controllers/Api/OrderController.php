<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\OrderService;
use App\DTOs\CreateLimitOrderDTO;
use App\Enums\Symbol;
use App\Enums\OrderSide;
use App\Http\Controllers\Controller;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService)
    {
    }
   
    public function index(Request $request)
    {
        $validated = $request->validate([
            'symbol' => ['nullable', Rule::in(Symbol::values())],
        ]);

        $orders = $this->orderService->getOpenOrders($validated['symbol']??null);
        return response()->json(['orders' => $orders, 'message' => 'Orders fetched successfully'], 200);
    }

   
    public function store(Request $request)
    {
        $validated = $request->validate([
            'symbol' => ['required', Rule::in(Symbol::values())],
            'side' => ['required', Rule::in(OrderSide::values())],
            'price' => ['required', 'numeric'],
            'amount' => ['required', 'numeric'],
        ]);

        $dto = CreateLimitOrderDTO::fromRequest($request->user(), $validated);
        $order = $this->orderService->createLimitOrder($dto);
        
        return response()->json(['order' => $order, 'message' => 'Order created successfully'], 201);
    }

  
    public function cancel(string $id)
    {
        $order = $this->orderService->cancelOrder($id);
        return response()->json(['order' => $order, 'message' => 'Order cancelled successfully'], 200);
    }
}
