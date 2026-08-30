<?php

namespace App\Http\Controllers\Api;

use App\Models\DiningTable;
use App\Models\TableZone;
use App\Repositories\DiningTableRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'zone_id' => ['nullable', 'integer', Rule::exists('table_zones', 'id')],
            'reserved_at' => ['nullable', 'date'],
            'store_id' => ['nullable', 'integer', 'exists:store,id'],
        ];
    }

    protected function updateRules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'zone_id' => ['sometimes', 'nullable', 'integer', Rule::exists('table_zones', 'id')],
            'reserved_at' => ['sometimes', 'nullable', 'date'],
            'store_id' => ['sometimes', 'nullable', 'integer', 'exists:store,id'],
        ];
    }

    /**
     * Sơ đồ bàn cho màn hình Order/List: trả các khu (table_zones) kèm
     * danh sách bàn của từng khu, trạng thái bàn suy ra từ dữ liệu thật
     * (không có cột trạng thái riêng trong DB).
     *
     * Quy tắc suy ra trạng thái:
     * - Có phiên đang mở (status=open, end_time=null) và phiên có đơn
     *   chưa thanh toán → 'occupied' (có khách).
     * - Có phiên đang mở nhưng chưa có đơn → 'ordering' (đang order).
     * - Không có phiên nhưng có reserved_at → 'reserved' (đã đặt).
     * - Còn lại → 'available' (trống).
     *
     * Thời gian hiển thị: started_at (phiên mở) cho bàn có khách/đang order,
     * reserved_at (giờ hẹn) cho bàn đã đặt, tổng tiền cộng từ các đơn
     * không bị huỷ của phiên hiện tại.
     */
    public function floor(Request $request): JsonResponse
    {
        $zones = TableZone::query()
            ->with([
                'diningTables' => fn ($query) => $query
                    ->orderBy('name')
                    ->with([
                        'tableSessions' => fn ($sessionQuery) => $sessionQuery
                            ->where('status', 'open')
                            ->whereNull('end_time')
                            ->with(['orders' => fn ($orderQuery) => $orderQuery->where('status', '!=', 'cancelled')]),
                    ]),
            ])
            ->orderBy('id')
            ->get();

        $payload = $zones->map(fn (TableZone $zone): array => [
            'id' => $zone->id,
            'name' => $zone->name,
            'tables' => $zone->diningTables
                ->map(fn (DiningTable $table): array => $this->floorTableRow($table))
                ->all(),
        ])->all();

        return $this->responder->success(['zones' => $payload]);
    }

    /**
     * Dựng một dòng dữ liệu bàn cho sơ đồ: trạng thái, mốc thời gian
     * (bắt đầu phiên hoặc giờ hẹn) và tổng tiền đơn hiện tại.
     *
     * @return array{id: int, name: string, zone_id: ?int, status: string, started_at: ?string, reserved_at: ?string, total: float}
     */
    private function floorTableRow(DiningTable $table): array
    {
        $activeSession = $table->tableSessions->first();
        $sessionOrders = $activeSession?->orders ?? collect();

        [$status, $startedAt] = match (true) {
            $activeSession !== null && $sessionOrders->isNotEmpty() => ['occupied', $activeSession->start_time],
            $activeSession !== null => ['ordering', $activeSession->start_time],
            $table->reserved_at !== null => ['reserved', null],
            default => ['available', null],
        };

        return [
            'id' => $table->id,
            'name' => $table->name,
            'zone_id' => $table->zone_id,
            'status' => $status,
            'started_at' => $startedAt?->toISOString(),
            'reserved_at' => $table->reserved_at?->toISOString(),
            'total' => (float) $sessionOrders->sum('total'),
        ];
    }
}
