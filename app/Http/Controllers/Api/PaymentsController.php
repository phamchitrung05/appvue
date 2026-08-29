<?php

namespace App\Http\Controllers\Api;

use App\Repositories\PaymentRepository;

/**
 * Class PaymentsController.
 */
class PaymentsController extends ApiCrudController
{
    protected function repositoryClass(): string
    {
        return PaymentRepository::class;
    }

    protected function createRules(): array
    {
        return [
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'string', 'max:50'],
            'paid_at' => ['nullable', 'date'],
        ];
    }

    protected function updateRules(): array
    {
        return [
            'order_id' => ['sometimes', 'nullable', 'integer', 'exists:orders,id'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'status' => ['sometimes', 'required', 'string', 'max:50'],
            'paid_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
