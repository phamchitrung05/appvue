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

        $tables = collect(range(1, 5))->map(fn (int $i): DiningTable => DiningTable::create([
            'name' => "Bàn {$i}",
            'store_id' => $stores->random()->id,
        ]));

        $sessions = $tables->map(fn (DiningTable $table): TableSession => TableSession::create([
            'dining_table_id' => $table->id,
            'start_time' => fake()->dateTimeBetween('-3 days', '-1 hours'),
            'end_time' => fake()->boolean(60) ? fake()->dateTimeBetween('-1 hours', 'now') : null,
            'status' => fake()->randomElement(['open', 'closed', 'cancelled']),
        ]));

        $orders = $sessions->map(fn (TableSession $session): Order => Order::create([
            'table_session_id' => $session->id,
            'status' => fake()->randomElement(['open', 'paid', 'cancelled']),
            'total' => fake()->numberBetween(50, 500) * 1000,
            'notes' => fake()->boolean(40) ? fake()->sentence() : null,
        ]));

        $orders->each(function (Order $order) use ($products): void {
            $product = $products->random();

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => fake()->numberBetween(1, 4),
                'unit_price' => $product->price,
                'notes' => fake()->boolean(30) ? 'Ít đá, ít đường' : null,
            ]);

            Payment::create([
                'order_id' => $order->id,
                'amount' => $order->total,
                'status' => $order->status === 'paid' ? 'completed' : 'pending',
                'paid_at' => $order->status === 'paid' ? now() : null,
            ]);
        });

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
}
