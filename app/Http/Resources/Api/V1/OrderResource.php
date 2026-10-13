<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'waiter_name' => $this->waiter_name,
            'customer_name' => $this->customer_name,
            'status' => $this->status,
            'total_amount' => (int) $this->total_amount,
            'payment_method' => $this->payment_method,
            'paid_at' => $this->paid_at,
            'cancellation_reason' => $this->cancellation_reason,
            'cancellation_requested_at' => $this->cancellation_requested_at,
            'void_code_approved' => $this->void_code_hash !== null,
            'void_code_issued' => $this->void_code_hash !== null
                && $this->void_code_expires_at !== null
                && now()->lt($this->void_code_expires_at),
            'items' => collect($this->items ?? [])->map(fn (object $item): array => [
                'id' => $item->id,
                'menu_item_id' => $item->menu_item_id,
                'name' => $item->menu_name,
                'unit_price' => (int) $item->unit_price,
                'quantity' => (int) $item->quantity,
                'line_total' => (int) $item->line_total,
            ])->values(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
