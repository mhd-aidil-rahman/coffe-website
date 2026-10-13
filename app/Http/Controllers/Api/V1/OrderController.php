<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use App\Support\OrderWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private OrderWorkflow $orders) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'status' => ['sometimes', Rule::in(['sent_to_kitchen', 'cancellation_pending', 'completed', 'cancelled'])]]);
        $query = $this->orders->query($request->user());
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        $orders = $query->paginate($request->integer('per_page', 50));
        $orders->setCollection($this->orders->withOrderItems($orders->getCollection()));

        return OrderResource::collection($orders);
    }

    public function show(Request $request, int $orderId): OrderResource
    {
        return new OrderResource($this->orders->findVisibleOrder($request->user(), $orderId));
    }

    public function store(Request $request): JsonResponse
    {
        return (new OrderResource($this->orders->create($request->user(), $request->only(['customer_name', 'items']))))->response()->setStatusCode(201);
    }

    public function pay(Request $request, int $orderId): JsonResponse
    {
        return response()->json(['data' => new OrderResource($this->orders->pay($request->user(), $orderId, $request->only(['payment_method'])))]);
    }

    public function requestCancellation(Request $request, int $orderId): JsonResponse
    {
        return response()->json(['data' => new OrderResource($this->orders->requestCancellation($request->user(), $orderId, $request->only(['reason'])))], 202);
    }

    public function void(Request $request, int $orderId): JsonResponse
    {
        return response()->json(['data' => new OrderResource($this->orders->void($request->user(), $orderId, $request->only(['void_code'])))]);
    }

    public function cancellationRequests(): AnonymousResourceCollection
    {
        $orders = $this->orders->pendingCancellations()->paginate(50);
        $orders->setCollection($this->orders->withOrderItems($orders->getCollection()));

        return OrderResource::collection($orders);
    }

    public function decideCancellation(Request $request, int $orderId): JsonResponse
    {
        $result = $this->orders->decideCancellation($orderId, $request->only(['decision']));
        $result['order'] = new OrderResource($result['order']);

        return response()->json(['data' => $result]);
    }
}
