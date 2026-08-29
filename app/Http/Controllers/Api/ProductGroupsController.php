<?php

namespace App\Http\Controllers\Api;

use App\Repositories\ProductGroupRepository;

/**
 * Class ProductGroupsController.
 */
class ProductGroupsController extends ApiCrudController
{
    protected function repositoryClass(): string
    {
        return ProductGroupRepository::class;
    }

    protected function createRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'store_id' => ['nullable', 'integer', 'exists:store,id'],
        ];
    }

    protected function updateRules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'nullable', 'integer'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'store_id' => ['sometimes', 'nullable', 'integer', 'exists:store,id'],
        ];
    }
}
