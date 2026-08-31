<?php

namespace App\Http\Controllers\Api;

use App\Repositories\TableZoneRepository;

/**
 * Controller CRUD cho khu vực bàn (table_zones).
 *
 * Mỗi khu thuộc một cửa hàng (store_id) và chứa nhiều bàn ăn — sơ đồ bàn
 * ở màn hình Order/List đọc dữ liệu khu từ đây. Index trả shape phân trang
 * gốc của Laravel (khu ít, không cần shape datatable riêng).
 */
class TableZonesController extends ApiCrudController
{
    protected function repositoryClass(): string
    {
        return TableZoneRepository::class;
    }

    protected function createRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'store_id' => ['nullable', 'integer', 'exists:store,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function updateRules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'store_id' => ['sometimes', 'nullable', 'integer', 'exists:store,id'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
