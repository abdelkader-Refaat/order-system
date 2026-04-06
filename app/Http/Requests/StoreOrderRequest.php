<?php

namespace App\Http\Requests;

use App\DTOs\CreateOrderDTO;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'string', 'email', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99999'],
            'items.*.price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function toCreateOrderDto(int $authenticatedUserId): CreateOrderDTO
    {
        $data = $this->validated();
        $data['user_id'] = $authenticatedUserId;

        return CreateOrderDTO::fromValidated($data);
    }
}
