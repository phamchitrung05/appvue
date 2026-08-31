<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Store;
use App\Models\TableSession;
use App\Models\TableZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kiểm tra endpoint sơ đồ bàn GET /api/v1/dining-tables/floor dùng cho
 * màn hình Order/List: trả các khu (table_zones) kèm bàn, trạng thái bàn
 * suy ra từ phiên + đơn, thời gian và tổng tiền trả về đúng từng trường hợp.
 */
class DiningTableFloorTest extends TestCase
{
    use RefreshDatabase;

    private function createZone(string $name = 'Trong nhà'): TableZone
    {
        return TableZone::create([
            'name' => $name,
            'is_active' => true,
            'store_id' => Store::create(['name' => 'Chi nhánh trung tâm', 'is_active' => true])->id,
        ]);
    }

    private function createTable(?TableZone $zone = null, string $name = '01'): DiningTable
    {
        return DiningTable::create([
            'name' => $name,
            'zone_id' => $zone?->id,
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

    public function test_floor_groups_tables_by_zone(): void
    {
        $zone = $this->createZone('Ngoài trời');
        $this->createTable($zone, 'T01');

        $this->getJson('/api/v1/dining-tables/floor')
            ->assertOk()
            ->assertJsonPath('data.zones.0.name', 'Ngoài trời')
            ->assertJsonPath('data.zones.0.tables.0.name', 'T01')
            ->assertJsonPath('data.zones.0.tables.0.status', 'available')
            ->assertJsonPath('data.zones.0.tables.0.total', 0);
    }

    public function test_floor_marks_table_with_open_session_and_order_as_occupied(): void
    {
        $table = $this->createTable($this->createZone());
        $session = $this->openSession($table, 45);

        Order::create([
            'table_session_id' => $session->id,
            'status' => 'open',
            'total' => 350000,
        ]);

        $this->getJson('/api/v1/dining-tables/floor')
            ->assertOk()
            ->assertJsonPath('data.zones.0.tables.0.status', 'occupied')
            ->assertJsonPath('data.zones.0.tables.0.total', 350000)
            ->assertJsonPath('data.zones.0.tables.0.started_at', $session->start_time->toISOString());
    }

    public function test_floor_marks_table_with_open_session_without_order_as_occupied(): void
    {
        // Luật 2 trạng thái: có phiên mở là "có khách", bất kể đã có đơn hay chưa
        $this->openSession($this->createTable($this->createZone()), 15);

        $this->getJson('/api/v1/dining-tables/floor')
            ->assertOk()
            ->assertJsonPath('data.zones.0.tables.0.status', 'occupied')
            ->assertJsonPath('data.zones.0.tables.0.total', 0);
    }

    public function test_floor_marks_reserved_table_without_session_as_available(): void
    {
        // Bàn có giờ hẹn nhưng chưa có phiên mở vẫn là "trống"
        $table = $this->createTable($this->createZone());
        $table->update(['reserved_at' => now()->setTime(14, 0)]);

        $this->getJson('/api/v1/dining-tables/floor')
            ->assertOk()
            ->assertJsonPath('data.zones.0.tables.0.status', 'available')
            ->assertJsonPath('data.zones.0.tables.0.reserved_at', $table->refresh()->reserved_at->toISOString());
    }

    public function test_floor_ignores_closed_sessions_when_resolving_status(): void
    {
        $table = $this->createTable($this->createZone());

        TableSession::create([
            'dining_table_id' => $table->id,
            'start_time' => now()->subHours(3),
            'end_time' => now()->subHour(),
            'status' => 'closed',
        ]);

        $this->getJson('/api/v1/dining-tables/floor')
            ->assertOk()
            ->assertJsonPath('data.zones.0.tables.0.status', 'available');
    }

    public function test_floor_sums_only_non_cancelled_orders_of_the_active_session(): void
    {
        $table = $this->createTable($this->createZone());
        $session = $this->openSession($table);

        Order::create(['table_session_id' => $session->id, 'status' => 'open', 'total' => 100000]);
        Order::create(['table_session_id' => $session->id, 'status' => 'paid', 'total' => 50000]);
        Order::create(['table_session_id' => $session->id, 'status' => 'cancelled', 'total' => 999999]);

        $this->getJson('/api/v1/dining-tables/floor')
            ->assertOk()
            ->assertJsonPath('data.zones.0.tables.0.status', 'occupied')
            ->assertJsonPath('data.zones.0.tables.0.total', 150000);
    }

    public function test_dining_table_validates_zone_id_against_table_zones(): void
    {
        $this->postJson('/api/v1/dining-tables', [
            'name' => 'Bàn lạ',
            'zone_id' => 424242,
        ])->assertStatus(422)->assertJsonValidationErrors(['zone_id']);

        $zone = $this->createZone();

        $this->postJson('/api/v1/dining-tables', [
            'name' => 'T01',
            'zone_id' => $zone->id,
        ])->assertCreated()->assertJsonPath('data.zone_id', $zone->id);
    }
}
