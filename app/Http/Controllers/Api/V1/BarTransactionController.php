<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BarTransactionResource;
use App\Http\Resources\Api\V1\MenuItemResource;
use App\Http\Resources\Api\V1\OrderResource;
use App\Support\BarOperations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BarTransactionController extends Controller
{
    public function __construct(private BarOperations $operations) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['period' => ['sometimes', Rule::in(['today', '7days', '30days'])]]);
        $period = $request->string('period')->toString() ?: 'today';
        $transactions = $this->operations->transactions($this->operations->periodStart($period));

        return response()->json(['data' => ['period' => $period, 'transactions' => BarTransactionResource::collection($transactions->take(50)->values())]]);
    }

    public function store(Request $request): JsonResponse
    {
        $transaction = $this->operations->create($request->user(), $request->only(['type', 'category', 'description', 'amount', 'notes']));

        return (new BarTransactionResource($transaction))->response()->setStatusCode(201);
    }

    public function operational(Request $request): JsonResponse
    {
        $request->validate(['period' => ['sometimes', Rule::in(['today', '7days', '30days'])]]);
        $data = $this->operations->operational($request->string('period')->toString() ?: 'today');
        $data['incoming_orders'] = OrderResource::collection($data['incoming_orders']);
        $data['active_menu_items'] = MenuItemResource::collection($data['active_menu_items']);
        $data['transactions'] = BarTransactionResource::collection($data['transactions']->take(50)->values());

        return response()->json(['data' => $data]);
    }
}
