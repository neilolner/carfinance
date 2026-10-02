<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'status'   => $this->status,
            'total'    => $this->total,
            'customer' => new CustomerResource($this->customer),
            'items'    => OrderItemResource::collection($this->items),
        ];
    }
}
