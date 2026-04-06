<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends ApiController
{
    public function store(StoreOrderRequest $request, OrderService $orderService): JsonResponse
    {
        //        using RealTime facade
        $order = $orderService->createOrder($request->toCreateOrderDto($request->user()->id));

        return $this->createdResponse(
            'messages.order.created',
            $this->resolveResource($request, new OrderResource($order)),
        );
    }
}
