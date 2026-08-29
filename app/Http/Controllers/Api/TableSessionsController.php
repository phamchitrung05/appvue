<?php

namespace App\Http\Controllers\Api;

use App\Repositories\TableSessionRepository;

/**
 * Class TableSessionsController.
 */
class TableSessionsController extends ApiCrudController
{
    protected function repositoryClass(): string
    {
        return TableSessionRepository::class;
    }

    protected function createRules(): array
    {
        return [
            'dining_table_id' => ['nullable', 'integer', 'exists:dining_table,id'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date', 'after_or_equal:start_time'],
            'status' => ['required', 'string', 'max:50'],
        ];
    }

    protected function updateRules(): array
    {
        return [
            'dining_table_id' => ['sometimes', 'nullable', 'integer', 'exists:dining_table,id'],
            'start_time' => ['sometimes', 'nullable', 'date'],
            'end_time' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_time'],
            'status' => ['sometimes', 'required', 'string', 'max:50'],
        ];
    }
}
