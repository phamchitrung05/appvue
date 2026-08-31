<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\TableZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kiểm tra CRUD khu vực bàn GET/POST/PUT/DELETE /api/v1/table-zones —
 * gồm cả trường hợp tạo mới KHÔNG gửi is_active (phải tự nhận giá trị
 * mặc định true thay vì lỗi 500 do cột NOT NULL).
 */
class TableZoneCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_zone_crud_lifecycle(): void
    {
        $store = Store::create(['name' => 'Chi nhánh trung tâm', 'is_active' => true]);

        $created = $this->postJson('/api/v1/table-zones', [
            'name' => 'Trong nhà',
            'store_id' => $store->id,
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.name', 'Trong nhà');

        $id = $created->json('data.id');

        $this->getJson('/api/v1/table-zones')->assertOk();
        $this->getJson("/api/v1/table-zones/{$id}")->assertOk();

        $this->putJson("/api/v1/table-zones/{$id}", ['name' => 'Tầng 1'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Tầng 1');

        $this->deleteJson("/api/v1/table-zones/{$id}")->assertNoContent();
        $this->getJson("/api/v1/table-zones/{$id}")->assertNotFound();
    }

    public function test_zone_created_without_is_active_defaults_to_true(): void
    {
        $created = $this->postJson('/api/v1/table-zones', ['name' => 'Ngoài trời'])
            ->assertCreated()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_zone_rejects_name_longer_than_column_width(): void
    {
        $this->postJson('/api/v1/table-zones', ['name' => str_repeat('a', 101)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_zone_rejects_unknown_store(): void
    {
        $this->postJson('/api/v1/table-zones', ['name' => 'Khu lỗi', 'store_id' => 9999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['store_id']);
    }

    public function test_zone_index_supports_search_and_sort_whitelist(): void
    {
        TableZone::create(['name' => 'Trong nhà', 'is_active' => true]);
        TableZone::create(['name' => 'Ngoài trời', 'is_active' => true]);

        $this->getJson('/api/v1/table-zones?q=Ngoài')->assertOk()->assertJsonCount(1, 'data.data');

        // Cột sort hợp lệ → 200; cột lạ ngoài $fieldSortable → vẫn 200 (bị bỏ qua).
        $this->getJson('/api/v1/table-zones?sortBy=name&orderBy=asc')->assertOk();
        $this->getJson('/api/v1/table-zones?sortBy=evil_column&orderBy=asc')->assertOk();
    }
}
