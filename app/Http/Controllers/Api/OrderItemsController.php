<?php

namespace App\Http\Controllers\Api;

use App\Repositories\OrderItemRepository;

/**
 * Class OrderItemsController.
 */
class OrderItemsController extends ApiCrudController
{
    protected function repositoryClass(): string
    {
        return OrderItemRepository::class;
    }

    protected function createRules(): array
    {
        return [
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'product_id' => ['nullable', 'integer', 'exists:product,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function updateRules(): array
    {
        return [
            'order_id' => ['sometimes', 'nullable', 'integer', 'exists:orders,id'],
            'product_id' => ['sometimes', 'nullable', 'integer', 'exists:product,id'],
            'quantity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'unit_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
