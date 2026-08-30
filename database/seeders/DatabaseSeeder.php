<?php

namespace Database\Seeders;

use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Printer;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Store;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with 5 fake records per model.
     */
    public function run(): void
    {
        $stores = collect(range(1, 5))->map(fn (int $i): Store => Store::create([
            'name' => "Chi nhánh {$i}",
            'address' => fake()->streetAddress(),
            'phone' => fake()->numerify('09########'),
            'email' => fake()->unique()->companyEmail(),
            'opening_hours' => '07:00-22:00',
            'is_active' => fake()->boolean(90),
        ]));

        $groups = collect(['Cà phê', 'Trà sữa', 'Nước ép', 'Đồ ăn nhẹ', 'Tráng miệng'])
            ->map(fn (string $name, int $i): ProductGroup => ProductGroup::create([
                'name' => $name,
                'sort_order' => $i + 1,
                'is_active' => true,
                'store_id' => $stores->random()->id,
            ]));

        $products = collect(['Cà phê sữa', 'Bạc xỉu', 'Trà đào cam sả', 'Latte', 'Bánh mì que'])
            ->map(fn (string $name): Product => Product::create([
                'name' => $name,
                'description' => fake()->sentence(),
                'price' => fake()->numberBetween(20, 80) * 1000,
                'product_group_id' => $groups->random()->id,
                'is_active' => true,
                'image_url' => fake()->imageUrl(640, 480, 'food'),
            ]));

        // ==================== Sơ đồ bàn ====================
        // 14 bàn trong nhà (01..14) + 6 bàn ngoài trời (T01..T06), độ trạng
        // thái giống màn hình Order/List: trống, có khách (phiên mở + đơn),
        // đang order (phiên mở chưa có đơn) và đã đặt (reserved_at).
        $tables = collect([
            ...collect(range(1, 14))->map(fn (int $i): array => ['name' => sprintf('%02d', $i), 'area' => 'indoor']),
            ...collect(range(1, 6))->map(fn (int $i): array => ['name' => sprintf('T%02d', $i), 'area' => 'outdoor']),
        ])->map(fn (array $attrs): DiningTable => DiningTable::create($attrs + ['store_id' => $stores->random()->id]));

        $tableByName = $tables->keyBy('name');

        // Bàn có khách: phiên mở + đơn chưa thanh toán.
        collect([
            ['name' => '03', 'minutes' => 45, 'total' => 350000],
            ['name' => '07', 'minutes' => 80, 'total' => 450000],
            ['name' => 'T02', 'minutes' => 35, 'total' => 220000],
        ])->each(fn (array $attrs): TableSession => $this->createOpenSession(
            $tableByName->get($attrs['name']),
            now()->subMinutes($attrs['minutes']),
            $attrs['total'],
        ));

        // Bàn đang order: phiên mở nhưng chưa chốt đơn nào.
        collect([['name' => '04', 'minutes' => 15], ['name' => 'T04', 'minutes' => 10]])
            ->each(fn (array $attrs): TableSession => $this->createOpenSession(
                $tableByName->get($attrs['name']),
                now()->subMinutes($attrs['minutes']),
            ));

        // Bàn đã đặt trước: chỉ set giờ hẹn, chưa có phiên.
        $tableByName->get('09')->update(['reserved_at' => now()->setTime(14, 0)]);
        $tableByName->get('T06')->update(['reserved_at' => now()->setTime(15, 0)]);

        collect(range(1, 5))->each(fn (int $i): Printer => Printer::create([
            'store_id' => $stores->random()->id,
            'name' => "Máy in {$i}",
            'printer_type' => fake()->randomElement(['receipt', 'kitchen', 'label']),
            'ip_address' => fake()->localIpv4(),
            'port' => 9100,
            'is_active' => true,
        ]));

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => 'password', 'store_id' => $stores->first()->id],
        );

        User::factory(4)->create(['store_id' => $stores->random()->id]);
    }

    /**
     * Tạo một phiên bàn đang mở, kèm đơn mở với tổng tiền tuỳ chọn
     * (có đơn → bàn "có khách", không đơn → bàn "đang order").
     */
    private function createOpenSession(DiningTable $table, \DateTimeInterface $startTime, ?int $total = null): TableSession
    {
        $session = TableSession::create([
            'dining_table_id' => $table->id,
            'start_time' => $startTime,
            'status' => 'open',
        ]);

        if ($total !== null) {
            Order::create([
                'table_session_id' => $session->id,
                'status' => 'open',
                'total' => $total,
            ]);
        }

        return $session;
    }
}
