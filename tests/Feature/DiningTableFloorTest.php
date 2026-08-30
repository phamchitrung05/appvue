<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Store;
use App\Models\TableSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kiểm tra endpoint sơ đồ bàn GET /api/v1/dining-tables/floor dùng cho
 * màn hình Order/List: trạng thái bàn suy ra từ phiên + đơn, thời gian
 * và tổng tiền trả về đúng từng trường hợp.
 */
class DiningTableFloorTest extends TestCase
{
    use RefreshDatabase;

    private function createTable(string $name = '01', ?string $area = 'indoor'): DiningTable
    {
        return DiningTable::create([
            'name' => $name,
            'area' => $area,
            'store_id' => Store::create(['name' => 'Chi nhánh trung tâm', 'is_active' => true])->id,
        ]);
    }

    private function openSession(DiningTable $table, int $minutesAgo = 30): TableSession
    {
        return TableSession::create([
            'dining_table_id' => $table->id,
            'start_time' => now()->subMinutes($minutesAgo),
            'status' => 'open',
        ]);
    }

    public function test_floor_returns_available_table_without_session(): void
    {
        $this->createTable();

        $this->getJson('/api/v1/dining-tables/floor')
            ->assertOk()
            ->assertJsonPath('data.tables.0.status', 'available')
            ->assertJsonPath('data.tables.0.total', 0)
            ->assertJsonPath('data.tables.0.area', 'indoor');
    }

    public function test_floor_marks_table_with_open_session_and_order_as_occupied(): void
    {
        $table = $this->createTable();
        $session = $this->openSession($table, 45);

        Order::create([
            'table_session_id' => $session->id,
            'status' => 'open',
            'total' => 350000,
        ]);

        $this->getJson('/api/v1/dining-tables/floor')
            ->assertOk()
            ->assertJsonPath('data.tables.0.status', 'occupied')
            ->assertJsonPath('data.tables.0.total', 350000)
            ->assertJsonPath('data.tables.0.started_at', $session->start_time->toISOString());
    }

    public function test_floor_marks_table_with_open_session_without_order_as_ordering(): void
    {
        $this->openSession($this->createTable(), 15);

        $this->getJson('/api/v1/dining-tables/floor')
            ->assertOk()
            ->assertJsonPath('data.tables.0.status', 'ordering')
            ->assertJsonPath('data.tables.0.total', 0);
    }

    public function test_floor_marks_reserved_table_without_session(): void
    {
        $reservedAt = now()->setTime(14, 0);

        $table = $this->createTable();
        $table->update(['reserved_at' => $reservedAt]);

        $this->getJson('/api/v1/dining-tables/floor')
            ->assertOk()
            ->assertJsonPath('data.tables.0.status', 'reserved')
            ->assertJsonPath('data.tables.0.reserved_at', $table->refresh()->reserved_at->toISOString());
    }

    public function test_floor_ignores_closed_sessions_when_resolving_status(): void
    {
        $table = $this->createTable();

        TableSession::create([
            'dining_table_id' => $table->id,
            'start_time' => now()->subHours(3),
            'end_time' => now()->subHour(),
            'status' => 'closed',
        ]);

        $this->getJson('/api/v1/dining-tables/floor')
            ->assertOk()
            ->assertJsonPath('data.tables.0.status', 'available');
    }

    public function test_floor_sums_only_non_cancelled_orders_of_the_active_session(): void
    {
        $table = $this->createTable();
        $session = $this->openSession($table);

        Order::create(['table_session_id' => $session->id, 'status' => 'open', 'total' => 100000]);
        Order::create(['table_session_id' => $session->id, 'status' => 'paid', 'total' => 50000]);
        Order::create(['table_session_id' => $session->id, 'status' => 'cancelled', 'total' => 999999]);

        $this->getJson('/api/v1/dining-tables/floor')
            ->assertOk()
            ->assertJsonPath('data.tables.0.status', 'occupied')
            ->assertJsonPath('data.tables.0.total', 150000);
    }

    public function test_floor_validates_area_on_create_via_crud_api(): void
    {
        $this->postJson('/api/v1/dining-tables', [
            'name' => 'Bàn lạ',
            'area' => 'tang-tret',
        ])->assertStatus(422)->assertJsonValidationErrors(['area']);

        $this->postJson('/api/v1/dining-tables', [
            'name' => 'T01',
            'area' => 'outdoor',
        ])->assertCreated()->assertJsonPath('data.area', 'outdoor');
    }
}
