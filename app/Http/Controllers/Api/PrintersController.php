<?php

namespace App\Http\Controllers\Api;

use App\Repositories\PrinterRepository;

/**
 * Class PrintersController.
 */
class PrintersController extends ApiCrudController
{
    protected function repositoryClass(): string
    {
        return PrinterRepository::class;
    }

    protected function createRules(): array
    {
        return [
            'store_id' => ['nullable', 'integer', 'exists:store,id'],
            'name' => ['required', 'string', 'max:255'],
            'printer_type' => ['nullable', 'string', 'max:50'],
            'ip_address' => ['nullable', 'ip'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function updateRules(): array
    {
        return [
            'store_id' => ['sometimes', 'nullable', 'integer', 'exists:store,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'printer_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'ip_address' => ['sometimes', 'nullable', 'ip'],
            'port' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
