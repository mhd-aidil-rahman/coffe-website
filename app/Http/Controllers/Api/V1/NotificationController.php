<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\OrderNotifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private OrderNotifications $notifications) {}

    public function completedOrders(Request $request): JsonResponse
    {
        return response()->json(['events' => $this->notifications->completed($request->only(['after_paid_at', 'after_order_id']))]);
    }
}
