<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EmployeeContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployeeContact */
class EmployeeContactResource extends JsonResource
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
            'type' => $this->type,
            'full_name' => $this->full_name,
            'relationship' => $this->relationship,
            'phone' => $this->phone,
            'address' => $this->address,
            'bank_name' => $this->bank_name,
            'bank_account_number' => $this->bank_account_number,
        ];
    }
}
