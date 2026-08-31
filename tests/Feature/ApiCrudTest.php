<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Store;
use App\Models\TableSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiCrudTest extends TestCase
{
    use RefreshDatabase;

    private function createStore(): Store
    {
        return Store::create([
            'name' => 'Chi nhánh trung tâm',
            'is_active' => true,
        ]);
    }

    private function createProductGroup(Store $store): ProductGroup
    {
        return ProductGroup::create([
            'name' => 'Cà phê',
            'store_id' => $store->id,
        ]);
    }

    private function createProduct(ProductGroup $group): Product
    {
        return Product::create([
            'name' => 'Cà phê sữa',
            'price' => 25000,
            'product_group_id' => $group->id,
        ]);
    }

    private function createDiningTable(Store $store): DiningTable
    {
        return DiningTable::create([
            'name' => 'Bàn 1',
            'store_id' => $store->id,
        ]);
    }

    private function createTableSession(DiningTable $table): TableSession
    {
        return TableSession::create([
            'dining_table_id' => $table->id,
            'start_time' => now(),
            'status' => 'open',
        ]);
    }

    private function createOrder(TableSession $session): Order
    {
        return Order::create([
            'table_session_id' => $session->id,
            'status' => 'open',
            'total' => 25000,
        ]);
    }

    public function test_store_crud_lifecycle(): void
    {
        $this->getJson('/api/v1/stores')
            ->assertOk()
            ->assertJsonStructure(['data' => ['data', 'page', 'per_page', 'total', 'last_page']]);

        $created = $this->postJson('/api/v1/stores', [
            'name' => 'Chi nhánh Quận 1',
            'address' => '123 Lê Lợi',
            'phone' => '0900000000',
            'email' => 'q1@example.com',
            'opening_hours' => '07:00-22:00',
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.name', 'Chi nhánh Quận 1');

        $id = $created->json('data.id');

        $this->getJson("/api/v1/stores/{$id}")
            ->assertOk()
            ->assertJsonPath('data.email', 'q1@example.com');

        $this->putJson("/api/v1/stores/{$id}", ['name' => 'Chi nhánh Quận 3'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Chi nhánh Quận 3');

        $this->deleteJson("/api/v1/stores/{$id}")->assertNoContent();

        $this->getJson("/api/v1/stores/{$id}")->assertNotFound();
    }

    public function test_store_validation_errors(): void
    {
        $this->postJson('/api/v1/stores', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'is_active']);
    }

    public function test_product_group_crud_lifecycle(): void
    {
        $store = $this->createStore();

        $created = $this->postJson('/api/v1/product-groups', [
            'name' => 'Trà sữa',
            'sort_order' => 2,
            'is_active' => true,
            'store_id' => $store->id,
        ])->assertCreated();

        $id = $created->json('data.id');

        $this->getJson('/api/v1/product-groups')->assertOk();
        $this->getJson("/api/v1/product-groups/{$id}")->assertOk();

        $this->putJson("/api/v1/product-groups/{$id}", ['sort_order' => 9])
            ->assertOk()
            ->assertJsonPath('data.sort_order', 9);

        $this->deleteJson("/api/v1/product-groups/{$id}")->assertNoContent();
    }

    public function test_product_group_rejects_unknown_store(): void
    {
        $this->postJson('/api/v1/product-groups', [
            'name' => 'Nhóm lỗi',
            'store_id' => 9999,
        ])->assertStatus(422)->assertJsonValidationErrors(['store_id']);
    }

    public function test_product_crud_lifecycle(): void
    {
        $group = $this->createProductGroup($this->createStore());

        $created = $this->postJson('/api/v1/products', [
            'name' => 'Trà đào',
            'description' => 'Trà đào cam sả',
            'price' => 45000,
            'product_group_id' => $group->id,
            'is_active' => true,
        ])->assertCreated();

        $id = $created->json('data.id');

        $this->getJson('/api/v1/products')->assertOk();
        $this->getJson("/api/v1/products/{$id}")->assertOk();

        $this->patchJson("/api/v1/products/{$id}", ['price' => 50000])
            ->assertOk()
            ->assertJsonPath('data.price', '50000.00');

        $this->deleteJson("/api/v1/products/{$id}")->assertNoContent();
    }

    public function test_dining_table_crud_lifecycle(): void
    {
        $store = $this->createStore();

        $created = $this->postJson('/api/v1/dining-tables', [
            'name' => 'Bàn VIP',
            'store_id' => $store->id,
        ])->assertCreated();

        $id = $created->json('data.id');

        $this->getJson('/api/v1/dining-tables')->assertOk();
        $this->getJson("/api/v1/dining-tables/{$id}")->assertOk();
        $this->putJson("/api/v1/dining-tables/{$id}", ['name' => 'Bàn VIP 2'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Bàn VIP 2');
        $this->deleteJson("/api/v1/dining-tables/{$id}")->assertNoContent();
    }

    public function test_dining_table_status_validation_and_default(): void
    {
        // Tạo mới KHÔNG gửi status → mặc định 'available' (bàn trống).
        $created = $this->postJson('/api/v1/dining-tables', ['name' => 'Bàn 01'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'available');

        $id = $created->json('data.id');

        // Đổi trạng thái hợp lệ.
        $this->putJson("/api/v1/dining-tables/{$id}", ['status' => 'occupied'])
            ->assertOk()
            ->assertJsonPath('data.status', 'occupied');

        // Trạng thái lạ bị chặn 422 thay vì lưu vào DB.
        $this->putJson("/api/v1/dining-tables/{$id}", ['status' => 'dang_don_dep'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        // Lọc danh sách theo status hoạt động (status nằm trong $fieldSearchable).
        $this->getJson('/api/v1/dining-tables?status=occupied')
            ->assertOk()
            ->assertJsonCount(1, 'data.data');
    }

    public function test_table_session_crud_lifecycle(): void
    {
        $table = $this->createDiningTable($this->createStore());

        $created = $this->postJson('/api/v1/table-sessions', [
            'dining_table_id' => $table->id,
            'start_time' => '2026-08-28 10:00:00',
            'status' => 'open',
        ])->assertCreated();

        $id = $created->json('data.id');

        $this->getJson('/api/v1/table-sessions')->assertOk();
        $this->getJson("/api/v1/table-sessions/{$id}")->assertOk();
        $this->putJson("/api/v1/table-sessions/{$id}", [
            'status' => 'closed',
            'end_time' => '2026-08-28 12:00:00',
        ])->assertOk()->assertJsonPath('data.status', 'closed');
        $this->deleteJson("/api/v1/table-sessions/{$id}")->assertNoContent();
    }

    public function test_order_crud_lifecycle(): void
    {
        $session = $this->createTableSession($this->createDiningTable($this->createStore()));

        $created = $this->postJson('/api/v1/orders', [
            'table_session_id' => $session->id,
            'status' => 'open',
            'total' => 120000,
            'notes' => 'Ít đá',
        ])->assertCreated();

        $id = $created->json('data.id');

        $this->getJson('/api/v1/orders')->assertOk();
        $this->getJson("/api/v1/orders/{$id}")->assertOk();
        $this->putJson("/api/v1/orders/{$id}", ['status' => 'paid'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');
        $this->deleteJson("/api/v1/orders/{$id}")->assertNoContent();
    }

    public function test_order_item_crud_lifecycle(): void
    {
        $store = $this->createStore();
        $product = $this->createProduct($this->createProductGroup($store));
        $order = $this->createOrder($this->createTableSession($this->createDiningTable($store)));

        $created = $this->postJson('/api/v1/order-items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 25000,
            'notes' => 'Không đường',
        ])->assertCreated();

        $id = $created->json('data.id');

        $this->getJson('/api/v1/order-items')->assertOk();
        $this->getJson("/api/v1/order-items/{$id}")->assertOk();
        $this->putJson("/api/v1/order-items/{$id}", ['quantity' => 5])
            ->assertOk()
            ->assertJsonPath('data.quantity', 5);
        $this->deleteJson("/api/v1/order-items/{$id}")->assertNoContent();
    }

    public function test_order_item_rejects_zero_quantity(): void
    {
        $this->postJson('/api/v1/order-items', [
            'quantity' => 0,
            'unit_price' => 1000,
        ])->assertStatus(422)->assertJsonValidationErrors(['quantity']);
    }

    public function test_payment_crud_lifecycle(): void
    {
        $order = $this->createOrder($this->createTableSession($this->createDiningTable($this->createStore())));

        $created = $this->postJson('/api/v1/payments', [
            'order_id' => $order->id,
            'amount' => 120000,
            'status' => 'pending',
        ])->assertCreated();

        $id = $created->json('data.id');

        $this->getJson('/api/v1/payments')->assertOk();
        $this->getJson("/api/v1/payments/{$id}")->assertOk();
        $this->putJson("/api/v1/payments/{$id}", [
            'status' => 'completed',
            'paid_at' => '2026-08-28 12:30:00',
        ])->assertOk()->assertJsonPath('data.status', 'completed');
        $this->deleteJson("/api/v1/payments/{$id}")->assertNoContent();
    }

    public function test_printer_crud_lifecycle(): void
    {
        $store = $this->createStore();

        $created = $this->postJson('/api/v1/printers', [
            'store_id' => $store->id,
            'name' => 'Máy in bếp',
            'printer_type' => 'kitchen',
            'ip_address' => '192.168.1.50',
            'port' => 9100,
            'is_active' => true,
        ])->assertCreated();

        $id = $created->json('data.id');

        $this->getJson('/api/v1/printers')->assertOk();
        $this->getJson("/api/v1/printers/{$id}")->assertOk();
        $this->putJson("/api/v1/printers/{$id}", ['port' => 9101])
            ->assertOk()
            ->assertJsonPath('data.port', 9101);
        $this->deleteJson("/api/v1/printers/{$id}")->assertNoContent();
    }

    public function test_printer_rejects_invalid_ip(): void
    {
        $this->postJson('/api/v1/printers', [
            'name' => 'Máy in lỗi',
            'ip_address' => 'not-an-ip',
            'is_active' => true,
        ])->assertStatus(422)->assertJsonValidationErrors(['ip_address']);
    }

    public function test_index_supports_request_criteria_search_and_pagination(): void
    {
        $store = $this->createStore();
        $group = $this->createProductGroup($store);

        Product::create(['name' => 'Bạc xỉu', 'price' => 30000, 'product_group_id' => $group->id]);
        Product::create(['name' => 'Espresso', 'price' => 40000, 'product_group_id' => $group->id]);
        Product::create(['name' => 'Latte', 'price' => 50000, 'product_group_id' => $group->id]);

        // Product luôn trả shape datatable (ProductsController override
        // indexResponse), kể cả khi phân trang bằng per_page.
        $this->getJson('/api/v1/products?per_page=2')
            ->assertOk()
            ->assertJsonPath('data.itemsPerPage', 2)
            ->assertJsonPath('data.total', 3)
            ->assertJsonCount(2, 'data.products');

        $this->getJson('/api/v1/products?search=Latte')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.products.0.name', 'Latte');
    }

    public function test_missing_record_returns_json_404(): void
    {
        $this->getJson('/api/v1/stores/424242')
            ->assertNotFound()
            ->assertJsonPath('code', 'STORE_NOT_FOUND');

        $this->putJson('/api/v1/stores/424242', ['name' => 'X'])->assertNotFound();
        $this->deleteJson('/api/v1/stores/424242')->assertNotFound();
    }

    private function seedThreeProducts(): void
    {
        $group = $this->createProductGroup($this->createStore());

        Product::create(['name' => 'Bạc xỉu', 'price' => 30000, 'product_group_id' => $group->id]);
        Product::create(['name' => 'Espresso', 'price' => 40000, 'product_group_id' => $group->id]);
        Product::create(['name' => 'Latte', 'price' => 50000, 'product_group_id' => $group->id]);
    }

    public function test_index_returns_data_table_shape_when_items_per_page_is_present(): void
    {
        $this->seedThreeProducts();

        $this->getJson('/api/v1/products?itemsPerPage=2&page=1')
            ->assertOk()
            ->assertJsonStructure(['data' => ['products', 'total', 'page', 'itemsPerPage', 'lastPage']])
            ->assertJsonMissingPath('data.data')
            ->assertJsonCount(2, 'data.products')
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.page', 1)
            ->assertJsonPath('data.itemsPerPage', 2)
            ->assertJsonPath('data.lastPage', 2);
    }

    public function test_data_table_format_can_be_requested_explicitly(): void
    {
        $this->seedThreeProducts();

        $this->getJson('/api/v1/products?format=datatable')
            ->assertOk()
            ->assertJsonCount(3, 'data.products')
            ->assertJsonPath('data.total', 3);
    }

    public function test_data_table_index_keeps_total_across_pages(): void
    {
        $this->seedThreeProducts();

        $this->getJson('/api/v1/products?itemsPerPage=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.page', 2);
    }

    public function test_data_table_index_maps_q_to_the_search_criteria(): void
    {
        $this->seedThreeProducts();

        $this->getJson('/api/v1/products?itemsPerPage=10&q=Latte')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.products.0.name', 'Latte');
    }

    public function test_data_table_index_searches_across_all_like_fields(): void
    {
        $this->seedThreeProducts();

        // Ô tìm kiếm chung quét mọi cột like: cả cột số (price) phải khớp
        // được, không chỉ name/description.
        $this->getJson('/api/v1/products?itemsPerPage=10&q=30000')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.products.0.name', 'Bạc xỉu');

        // Cột điều kiện '=' (is_active, product_group_id) KHÔNG tham gia
        // search: chuỗi ký tự bất kỳ không được khớp nhầm boolean trên MySQL.
        $this->getJson('/api/v1/products?itemsPerPage=10&q=zzz_khong_ton_tai')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_data_table_index_maps_sort_by_and_order_by(): void
    {
        $this->seedThreeProducts();

        $this->getJson('/api/v1/products?itemsPerPage=10&sortBy=name&orderBy=desc')
            ->assertOk()
            ->assertJsonPath('data.products.0.name', 'Latte');

        $this->getJson('/api/v1/products?itemsPerPage=10&sortBy=name&orderBy=asc')
            ->assertOk()
            ->assertJsonPath('data.products.0.name', 'Bạc xỉu');
    }

    public function test_data_table_index_sorts_by_price(): void
    {
        $this->seedThreeProducts();

        $this->getJson('/api/v1/products?itemsPerPage=10&sortBy=price&orderBy=asc')
            ->assertOk()
            ->assertJsonPath('data.products.0.name', 'Bạc xỉu');

        $this->getJson('/api/v1/products?itemsPerPage=10&sortBy=price&orderBy=desc')
            ->assertOk()
            ->assertJsonPath('data.products.0.name', 'Latte');
    }

    public function test_data_table_index_ignores_sort_by_unknown_column(): void
    {
        $this->seedThreeProducts();

        // Cột không nằm trong $fieldSortable phải bị bỏ qua (vẫn 200 và đủ
        // dữ liệu) thay vì đẩy thẳng vào ORDER BY gây lỗi SQL 500.
        $this->getJson('/api/v1/products?itemsPerPage=10&sortBy=evil_column&orderBy=asc')
            ->assertOk()
            ->assertJsonCount(3, 'data.products')
            ->assertJsonPath('data.total', 3);
    }

    public function test_data_table_index_returns_every_row_when_items_per_page_is_negative(): void
    {
        $this->seedThreeProducts();

        $this->getJson('/api/v1/products?itemsPerPage=-1')
            ->assertOk()
            ->assertJsonCount(3, 'data.products')
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.lastPage', 1);
    }

    public function test_data_table_index_returns_an_empty_list_when_no_rows_match(): void
    {
        $this->seedThreeProducts();

        $this->getJson('/api/v1/products?itemsPerPage=10&q=KhongTonTai')
            ->assertOk()
            ->assertJsonPath('data.products', [])
            ->assertJsonPath('data.total', 0);
    }

    public function test_data_table_items_key_is_camel_cased_plural_of_the_model(): void
    {
        $store = $this->createStore();

        // Product là resource duy nhất override indexResponse sang shape
        // datatable — key items là số nhiều camel case của model.
        $this->getJson('/api/v1/products?itemsPerPage=10')
            ->assertOk()
            ->assertJsonStructure(['data' => ['products', 'total']]);

        // Các resource còn lại trả shape chuẩn hoá mặc định (dòng ở data.data).
        $this->getJson('/api/v1/dining-tables?itemsPerPage=10')
            ->assertOk()
            ->assertJsonStructure(['data' => ['data', 'page']]);

        $this->createTableSession($this->createDiningTable($store));

        $this->getJson('/api/v1/table-sessions?itemsPerPage=10')
            ->assertOk()
            ->assertJsonStructure(['data' => ['data', 'page']]);
    }

    public function test_index_still_returns_the_paginator_shape_without_data_table_params(): void
    {
        $store = $this->createStore();
        Store::create(['name' => 'Chi nhánh 2', 'is_active' => true]);

        // Store không override indexResponse — luôn là shape mặc định chuẩn
        // hoá, không bao giờ có key products của shape datatable.
        $this->getJson('/api/v1/stores?per_page=2')
            ->assertOk()
            ->assertJsonStructure(['data' => ['data', 'page', 'per_page', 'total', 'last_page']])
            ->assertJsonCount(2, 'data.data')
            ->assertJsonMissingPath('data.products');
    }
}
