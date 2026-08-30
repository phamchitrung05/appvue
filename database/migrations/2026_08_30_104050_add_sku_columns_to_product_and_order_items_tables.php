<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bổ sung 2 cột còn thiếu so với schema thiết kế:
     * - product.is_sku: cờ đánh dấu sản phẩm có SKU (biến thể) hay không.
     * - order_items.product_sku_id: SKU cụ thể được gọi trong món (null nếu
     *   sản phẩm không có SKU).
     */
    public function up(): void
    {
        Schema::table('product', function (Blueprint $table) {
            $table->boolean('is_sku')->default(false)->comment('Sản phẩm có SKU (biến thể) hay không')->after('image_url');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_sku_id')->nullable()->after('product_id')->constrained('product_sku');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_sku_id');
        });

        Schema::table('product', function (Blueprint $table) {
            $table->dropColumn('is_sku');
        });
    }
};
