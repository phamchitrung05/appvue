<?php

namespace Database\Seeders;

use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Printer;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Store;
use App\Models\TableSession;
use App\Models\TableZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeder tạo dữ liệu giả lập cho toàn bộ các bảng của hệ thống POS.
 *
 * Nguyên tắc:
 * - Mỗi bảng được bù lên tới ít nhất 20 dòng (bảng đã đủ thì bỏ qua).
 * - Dữ liệu tuân theo quan hệ khoá ngoại: store → zone / nhóm hàng / bàn /
 *   máy in → phiên bàn → đơn hàng → món trong đơn + thanh toán.
 * - Dùng firstOrCreate / kiểm tra số lượng nên chạy lại nhiều lần không sinh
 *   bản ghi trùng.
 * - Kịch bản bàn ăn: xen kẽ các trạng thái (có khách, đang order, đã đặt,
 *   trống) để sơ đồ bàn ở Order/List hiển thị sinh động.
 */
class FakeDataSeeder extends Seeder
{
    /**
     * Số dòng tối thiểu cho mỗi bảng.
     */
    protected int $minRows = 20;

    /**
     * Chạy tuần tự theo thứ tự phụ thuộc khoá ngoại.
     */
    public function run(): void
    {
        $this->seedStores();
        $this->seedTableZones();
        $this->seedProductGroups();
        $this->seedProducts();
        $this->seedDiningTables();
        $this->seedTableSessions();
        $this->seedOrders();
        $this->seedOrderItems();
        $this->seedPayments();
        $this->seedPrinters();
    }

    /**
     * Cửa hàng: bù tới ít nhất 20 chi nhánh.
     */
    protected function seedStores(): void
    {
        // 👉 2 cửa hàng đặt tên tiếng Việt (giữ nguyên từ phiên trước)
        $stores = [
            [
                'name' => 'Cửa hàng Tân Bình',
                'address' => '124 Cộng Hòa, Phường 4, Tân Bình',
                'phone' => '02838111222',
                'email' => 'tanbinh@pos.vn',
                'opening_hours' => '07:00-22:00',
                'is_active' => true,
            ],
            [
                'name' => 'Cửa hàng Thủ Đức',
                'address' => '275 Võ Văn Ngân, Phường Bình Thọ, Thủ Đức',
                'phone' => '02837222333',
                'email' => 'thuduc@pos.vn',
                'opening_hours' => '06:30-21:30',
                'is_active' => true,
            ],
        ];

        foreach ($stores as $store) {
            Store::firstOrCreate(['name' => $store['name']], $store);
        }

        // Bù tới 20 cửa hàng với tên sinh tự động
        $districts = ['Quận 1', 'Quận 3', 'Quận 5', 'Quận 10', 'Bình Thạnh', 'Gò Vấp', 'Phú Nhuận', 'Quận 7', 'Quận 8', 'Quận 12', 'Bình Tân', 'Tân Phú', 'Cần Giờ', 'Củ Chi'];

        while (Store::count() < $this->minRows) {
            $index = Store::count() + 1;
            $district = $districts[($index - 3) % count($districts)] ?? 'Quận '.$index;

            Store::firstOrCreate(
                ['name' => "Cửa hàng {$district} {$index}"],
                [
                    'address' => "{$index} Đường Số {$index}, {$district}",
                    'phone' => '0900'.str_pad((string) $index, 6, '0', STR_PAD_LEFT),
                    'email' => "chinhanh{$index}@pos.vn",
                    'opening_hours' => '07:00-22:00',
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Khu vực bàn: bù tới 20 khu, xoay vòng giữa các cửa hàng.
     */
    protected function seedTableZones(): void
    {
        $zoneNames = ['Khu trong nhà', 'Khu ngoài trời', 'Khu VIP', 'Khu sân thượng', 'Khu phòng riêng', 'Khu quầy bar'];
        $storeIds = Store::pluck('id')->all();

        if (empty($storeIds)) {
            return;
        }

        $index = 0;

        while (TableZone::count() < $this->minRows) {
            $name = $zoneNames[$index % count($zoneNames)];
            $storeId = $storeIds[$index % count($storeIds)];
            $suffix = intdiv($index, count($zoneNames)) > 0 ? ' '.(intdiv($index, count($zoneNames)) + 1) : '';

            TableZone::firstOrCreate(
                ['name' => $name.$suffix, 'store_id' => $storeId],
                ['is_active' => true],
            );

            $index++;
        }
    }

    /**
     * Nhóm hàng: bù tới 20 nhóm.
     */
    protected function seedProductGroups(): void
    {
        $groupNames = ['Nước suối', 'Khai vị', 'Món lá', 'Đồ chiên', 'Cơm', 'Mì - Phở', 'Hoa quả dĩa', 'Pudding', 'Cà phê mang đi', 'Combo gia đình'];
        $storeIds = Store::pluck('id')->all();

        $index = 0;

        while (ProductGroup::count() < $this->minRows) {
            $name = $groupNames[$index % count($groupNames)].(intdiv($index, count($groupNames)) > 0 ? ' '.(intdiv($index, count($groupNames)) + 1) : '');

            ProductGroup::firstOrCreate(
                ['name' => $name],
                [
                    'sort_order' => ProductGroup::count() + 1,
                    'is_active' => true,
                    'store_id' => empty($storeIds) ? null : $storeIds[$index % count($storeIds)],
                ],
            );

            $index++;
        }
    }

    /**
     * Sản phẩm: gán vòng tròn vào các nhóm hiện có (bảng đã ≥ 20 thì bỏ qua).
     */
    protected function seedProducts(): void
    {
        if (Product::count() >= $this->minRows) {
            return;
        }

        $groupIds = ProductGroup::pluck('id')->all();

        if (empty($groupIds)) {
            return;
        }

        $products = [
            ['Espresso', 25000, 'Cà phê nguyên chất đậm vị'],
            ['Cappuccino', 38000, 'Cà phê Ý với lớp sữa foam mịn'],
            ['Mocha đá xay', 45000, 'Socola đắng hòa quyện cùng cà phê'],
            ['Cold brew cam', 42000, 'Cold brew ủ 16 giờ hòa vị cam'],
            ['Trà xanh Matcha Latte', 48000, 'Matcha Uji pha cùng sữa tươi'],
            ['Trà vải', 35000, 'Trà thơm vải ngọt mát'],
            ['Trà ô long peach', 39000, 'Ô long hương đào tự nhiên'],
            ['Nước mía', 22000, 'Mía nguyên chất ép lạnh'],
            ['Soda chanh', 25000, 'Soda chanh tươi mát'],
            ['Sữa tươi trân châu đường đen', 36000, 'Đường đen thơm, trân châu dai'],
            ['Sinh tố bơ', 42000, 'Bơ sáp kem mịn'],
            ['Sinh tố dâu', 38000, 'Dâu Đà Lạt tươi nguyên'],
            ['Yaourt dâu', 32000, 'Yaourt nhà trộn dâu tươi'],
            ['Bánh su kem', 28000, 'Vỏ bánh bông lan mềm, nhân kem'],
            ['Croissant bơ', 33000, 'Vỏ sừng bò nướng bơ giòn'],
            ['Bánh mì chảo', 45000, 'Bánh mì chảo trứng phô mai'],
            ['Baguette pate', 40000, 'Baguette giòn, pate thơm'],
            ['Bánh custard', 26000, 'Custard mềm, vỏ giòn'],
            ['Chè khúc bạch', 35000, 'Khúc bạch hạnh nhân, nhãn'],
            ['Kem flan', 24000, 'Flan caramel thơm trứng'],
        ];

        foreach ($products as $index => [$name, $price, $description]) {
            Product::firstOrCreate(
                ['name' => $name],
                [
                    'description' => $description,
                    'price' => $price,
                    'product_group_id' => $groupIds[$index % count($groupIds)],
                    'is_active' => $index % 7 !== 3,
                ],
            );
        }
    }

    /**
     * Bàn ăn: bù tới 20 bàn, gán vào khu + cửa hàng tương ứng.
     */
    protected function seedDiningTables(): void
    {
        $zones = TableZone::query()->orderBy('id')->get(['id', 'store_id']);

        if ($zones->isEmpty()) {
            return;
        }

        while (DiningTable::count() < $this->minRows) {
            $number = DiningTable::count() + 1;
            $zone = $zones[($number - 1) % $zones->count()];

            DiningTable::firstOrCreate(
                ['name' => 'Bàn '.str_pad((string) $number, 2, '0', STR_PAD_LEFT)],
                [
                    'zone_id' => $zone->id,
                    'store_id' => $zone->store_id,
                    'reserved_at' => null,
                ],
            );
        }

        // Bàn theo kịch bản index % 5 == 4 → "đã đặt": đặt giờ hẹn trong tương lai
        DiningTable::query()->orderBy('id')->get(['id', 'reserved_at'])
            ->values()
            ->each(function ($table, $index) {
                if ($index % 5 === 4 && ! $table->reserved_at) {
                    DiningTable::where('id', $table->id)->update([
                        'reserved_at' => Carbon::now()->addHours(2 + $index),
                    ]);
                }
            });
    }

    /**
     * Phiên bàn: tạo kịch bản trạng thái xen kẽ để sơ đồ bàn sinh động.
     *
     * Chia 5 theo thứ tự bàn: 2 bàn có phiên mở (sẽ thành "có khách" khi có
     * đơn), 2 bàn có phiên mở không đơn ("đang order"), 1 bàn đặt trước
     * (reserved_at trong tương lai), các bàn còn lại để trống.
     */
    protected function seedTableSessions(): void
    {
        $tables = DiningTable::query()->orderBy('id')->get(['id', 'reserved_at']);

        foreach ($tables as $index => $table) {
            $pattern = $index % 5;

            // Chỉ tạo phiên mở nếu bàn chưa có phiên nào đang mở
            $hasOpenSession = TableSession::where('dining_table_id', $table->id)
                ->where('status', 'open')
                ->exists();

            if ($hasOpenSession) {
                continue;
            }

            if ($pattern === 0 || $pattern === 1) {
                TableSession::create([
                    'dining_table_id' => $table->id,
                    'start_time' => Carbon::now()->subMinutes(25 + $index * 7),
                    'end_time' => null,
                    'status' => 'open',
                ]);
            } elseif ($pattern === 2 || $pattern === 3) {
                TableSession::create([
                    'dining_table_id' => $table->id,
                    'start_time' => Carbon::now()->subMinutes(15 + $index * 5),
                    'end_time' => null,
                    'status' => 'open',
                ]);
            }
        }

        // Bổ sung phiên đóng (lịch sử) tới đủ 20 dòng
        while (TableSession::count() < $this->minRows) {
            $table = DiningTable::query()->orderBy('id')->skip(TableSession::count() % max(DiningTable::count(), 1))->first();

            if (! $table) {
                return;
            }

            $start = Carbon::now()->subDays(1 + intdiv(TableSession::count(), 4))->setTime(11, 30);

            TableSession::create([
                'dining_table_id' => $table->id,
                'start_time' => $start,
                'end_time' => $start->copy()->addHours(2),
                'status' => 'closed',
            ]);
        }
    }

    /**
     * Đơn hàng: gắn vào phiên — đơn "open" cho phiên đang mở của bàn có khách,
     * đơn "paid" cho các phiên đóng (lịch sử).
     */
    protected function seedOrders(): void
    {
        $openSessions = TableSession::where('status', 'open')->orderBy('id')->get();
        $closedSessions = TableSession::where('status', 'closed')->orderBy('id')->get();

        // Đơn đang mở cho phiên open — CHỈ bàn theo kịch bản index % 5 ∈ {0,1}
        // ("có khách"); bàn % 5 ∈ {2,3} cố ý KHÔNG có đơn để hiển thị "đang order"
        foreach ($openSessions as $session) {
            $tableIndex = $tables->search(fn ($table) => $table->id === $session->dining_table_id);

            if ($tableIndex === false || $tableIndex % 5 === 2 || $tableIndex % 5 === 3) {
                continue;
            }

            $hasOrder = Order::where('table_session_id', $session->id)->exists();

            if (! $hasOrder) {
                Order::create([
                    'table_session_id' => $session->id,
                    'status' => 'open',
                    'total' => 0,
                    'notes' => null,
                ]);
            }
        }

        // Đơn đã thanh toán cho các phiên đóng tới đủ 20 dòng
        // (chỉ chọn phiên CHƯA có đơn paid — tránh lặp vô hạn khi firstOrCreate bỏ qua)
        $availableSessions = $closedSessions->filter(
            fn ($session) => ! Order::where('table_session_id', $session->id)->where('status', 'paid')->exists(),
        )->values();

        while (Order::count() < $this->minRows && $availableSessions->isNotEmpty()) {
            $session = $availableSessions->shift();

            Order::create([
                'table_session_id' => $session->id,
                'status' => 'paid',
                'total' => 0,
                'notes' => null,
            ]);
        }
    }

    /**
     * Món trong đơn: mỗi đơn lấy 2-3 sản phẩm ngẫu nhiên, tính lại tổng đơn.
     */
    protected function seedOrderItems(): void
    {
        $products = Product::query()->orderBy('id')->get(['id', 'price']);

        if ($products->isEmpty()) {
            return;
        }

        $orders = Order::query()->orderBy('id')->get();

        foreach ($orders as $orderIndex => $order) {
            if (OrderItem::where('order_id', $order->id)->exists()) {
                continue;
            }

            $total = 0;
            $picked = $products->slice(($orderIndex * 2) % max($products->count() - 2, 0), 3);

            foreach ($picked as $product) {
                $quantity = ($orderIndex + $product->id) % 3 + 1;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'notes' => $orderIndex % 4 === 0 ? 'Ít đá' : null,
                ]);

                $total += $quantity * (float) $product->price;
            }

            $order->update(['total' => $total]);
        }
    }

    /**
     * Thanh toán: đơn "paid" → completed với paid_at; đơn "open" → pending.
     */
    protected function seedPayments(): void
    {
        $orders = Order::query()->orderBy('id')->get();

        foreach ($orders as $index => $order) {
            if (Payment::where('order_id', $order->id)->exists()) {
                continue;
            }

            $isPaid = $order->status === 'paid';

            Payment::create([
                'order_id' => $order->id,
                'amount' => $order->total,
                'status' => $isPaid ? 'completed' : 'pending',
                'paid_at' => $isPaid ? Carbon::now()->subHours($index % 10 + 1) : null,
            ]);
        }
    }

    /**
     * Máy in: bù tới 20 máy phân bố theo cửa hàng.
     */
    protected function seedPrinters(): void
    {
        $storeIds = Store::pluck('id')->all();
        $types = ['kitchen', 'bill'];

        while (Printer::count() < $this->minRows) {
            $index = Printer::count() + 1;
            $storeId = empty($storeIds) ? null : $storeIds[$index % count($storeIds)];

            Printer::firstOrCreate(
                ['name' => 'Máy in '.$types[$index % 2].' '.$index],
                [
                    'store_id' => $storeId,
                    'printer_type' => $types[$index % 2],
                    'ip_address' => '192.168.1.'.($index % 250 + 2),
                    'port' => 9100,
                    'is_active' => true,
                ],
            );
        }
    }
}
