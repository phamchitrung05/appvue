<?php

namespace App\Http\Controllers\Api;

use App\Repositories\ProductRepository;

/**
 * Class ProductsController.
 */
class ProductsController extends ApiCrudController
{
    protected function repositoryClass(): string
    {
        return ProductRepository::class;
    }

    protected function createRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'product_group_id' => ['nullable', 'integer', 'exists:product_group,id'],
            'is_active' => ['nullable', 'boolean'],
            'image_url' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function updateRules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'product_group_id' => ['sometimes', 'nullable', 'integer', 'exists:product_group,id'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
