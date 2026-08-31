<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm cột status lưu trạng thái hiện tại của bàn ăn thay vì suy diễn
     * từ phiên bàn: 'available' (trống) / 'occupied' (có khách — có phiên
     * đang mở). Mặc định 'available' cho cả dữ liệu cũ.
     */
    public function up(): void
    {
        Schema::table('dining_table', function (Blueprint $table) {
            $table->string('status', 20)->default('available')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('dining_table', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
