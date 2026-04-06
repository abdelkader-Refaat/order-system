<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends ApiController
{
    public function store(StoreOrderRequest $request, OrderService $orderService): JsonResponse
    {
        $order = $orderService->createOrder($request->toCreateOrderDto($request->user()->id));

        return $this->createdResponse(
            'messages.order.created',
            $this->resolveResource($request, new OrderResource($order)),
        );
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return $this->forbiddenResponse();
        }

        return $this->successResponse(
            'messages.order.retrieved',
            $this->resolveResource($request, new OrderResource($order->fresh())),
        );
    }
}
