<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BarTransactionResource extends JsonResource
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
            'source' => $this->source ?? 'bar',
            'type' => $this->type,
            'category' => $this->category,
            'description' => $this->description,
            'amount' => (int) $this->amount,
            'notes' => $this->notes,
            'staff_name' => $this->staff_name ?? null,
            'occurred_at' => $this->occurred_at,
        ];
    }
}
