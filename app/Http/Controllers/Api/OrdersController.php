<?php

namespace App\Http\Controllers\Api;

use App\Repositories\OrderRepository;

/**
 * Class OrdersController.
 */
class OrdersController extends ApiCrudController
{
    protected function repositoryClass(): string
    {
        return OrderRepository::class;
    }

    protected function createRules(): array
    {
        return [
            'table_session_id' => ['nullable', 'integer', 'exists:table_sessions,id'],
            'status' => ['required', 'string', 'max:50'],
            'total' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function updateRules(): array
    {
        return [
            'table_session_id' => ['sometimes', 'nullable', 'integer', 'exists:table_sessions,id'],
            'status' => ['sometimes', 'required', 'string', 'max:50'],
            'total' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
