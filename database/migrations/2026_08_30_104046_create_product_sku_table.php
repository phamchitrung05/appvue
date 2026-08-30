<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng product_sku: biến thể (SKU) của sản phẩm — mỗi dòng là một
     * phiên bản bán được với giá/tồn kho riêng (size, topping...).
     */
    public function up(): void
    {
        Schema::create('product_sku', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('product');
            $table->string('sku', 100)->unique();
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sku');
    }
};
