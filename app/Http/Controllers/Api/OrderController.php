<?php

namespace App\Http\Controllers\Api;

use App\DTOs\CreateLimitOrderDTO;
use App\Enums\OrderSide;
use App\Enums\Symbol;
use App\Models\Order;
use App\Services\OrderService;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService)
    {
    }
   
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'symbol' => ['nullable', Rule::in(Symbol::values())],
        ]);

        try {
            $orders = $this->orderService->getOpenOrders($validated['symbol'] ?? null);
            
            return response()->json([
                'orders' => OrderResource::collection($orders),
                'message' => 'Orders fetched successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch orders',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

   
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'symbol' => ['required', Rule::in(Symbol::values())],
            'side' => ['required', Rule::in(OrderSide::values())],
            'price' => ['required', 'numeric', 'min:0.01'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $user = $request->user();
            // Ensure we're using the correct authenticated user
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthenticated',
                    'error' => 'User not authenticated',
                ], 401);
            }
            $dto = CreateLimitOrderDTO::fromRequest($user, $validated);
            $order = $this->orderService->createLimitOrder($dto);
            $order->load('user');
            
            return response()->json([
                'order' => new OrderResource($order),
                'message' => 'Order created successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create order',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

  
    public function myOrders(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $orders = Order::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json([
                'orders' => OrderResource::collection($orders),
                'message' => 'Orders fetched successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch orders',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        try {
            $order = Order::findOrFail($id);
            
            if ($order->user_id !== $request->user()->id) {
                return response()->json([
                    'message' => 'Unauthorized',
                    'error' => 'You can only cancel your own orders',
                ], 403);
            }

            $cancelledOrder = $this->orderService->cancelOrder($id);
            $cancelledOrder->load('user');
            
            return response()->json([
                'order' => new OrderResource($cancelledOrder),
                'message' => 'Order cancelled successfully',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Order not found',
                'error' => 'The requested order does not exist',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to cancel order',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
