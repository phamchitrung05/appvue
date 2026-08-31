<?php

namespace App\Http\Controllers\Api;

use App\Repositories\DiningTableRepository;
use Illuminate\Validation\Rule;

/**
 * Class DiningTablesController.
 */
class DiningTablesController extends ApiCrudController
{
    protected function repositoryClass(): string
    {
        return DiningTableRepository::class;
    }

    protected function createRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Trạng thái hiện tại của bàn — khớp TABLE_STATUSES phía frontend
            // ('available' = trống, 'occupied' = có khách/phiên đang mở).
            'status' => ['nullable', 'string', 'max:20', Rule::in(['available', 'occupied'])],
            'zone_id' => ['nullable', 'integer', Rule::exists('table_zones', 'id')],
            'reserved_at' => ['nullable', 'date'],
            'store_id' => ['nullable', 'integer', 'exists:store,id'],
        ];
    }

    protected function updateRules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', 'max:20', Rule::in(['available', 'occupied'])],
            'zone_id' => ['sometimes', 'nullable', 'integer', Rule::exists('table_zones', 'id')],
            'reserved_at' => ['sometimes', 'nullable', 'date'],
            'store_id' => ['sometimes', 'nullable', 'integer', 'exists:store,id'],
        ];
    }
}
