<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng table_zones: danh sách khu vực bàn theo chi nhánh
     * (ví dụ: trong nhà, ngoài trời, tầng 2...).
     */
    public function up(): void
    {
        Schema::create('table_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('Tên khu vực, ví dụ: trong nhà, ngoài trời');
            $table->foreignId('store_id')->nullable()->constrained('store');
            $table->boolean('is_active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_zones');
    }
};
