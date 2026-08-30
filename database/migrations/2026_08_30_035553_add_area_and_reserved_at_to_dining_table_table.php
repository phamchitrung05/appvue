<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bổ sung cho bảng dining_table phục vụ màn hình sơ đồ bàn (Order/List):
     * - area: khu vực đặt bàn ('indoor' = trong nhà, 'outdoor' = ngoài trời),
     *   null coi như trong nhà để tương thích dữ liệu cũ.
     * - reserved_at: thời gian khách đặt trước; bàn có giá trị này (và chưa có
     *   phiên mở) hiển thị trạng thái "Đã đặt".
     */
    public function up(): void
    {
        Schema::table('dining_table', function (Blueprint $table) {
            $table->string('area', 20)->nullable()->after('name');
            $table->timestamp('reserved_at')->nullable()->after('area');
        });
    }

    public function down(): void
    {
        Schema::table('dining_table', function (Blueprint $table) {
            $table->dropColumn(['area', 'reserved_at']);
        });
    }
};
