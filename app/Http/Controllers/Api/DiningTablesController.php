<?php

namespace App\Http\Controllers\Api;

use App\Repositories\DiningTableRepository;

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
            'store_id' => ['nullable', 'integer', 'exists:store,id'],
        ];
    }

    protected function updateRules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'store_id' => ['sometimes', 'nullable', 'integer', 'exists:store,id'],
        ];
    }
}
