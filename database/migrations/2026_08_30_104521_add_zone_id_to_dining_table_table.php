<?php

use App\Models\DiningTable;
use App\Models\TableZone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chuyển dining_table từ cột area (key 'indoor'/'outdoor') sang
     * zone_id tham chiếu bảng table_zones đúng theo schema thiết kế:
     * - Tạo zone tương ứng cho từng giá trị area đang có trong dữ liệu.
     * - Trỏ zone_id của các bàn sang zone vừa tạo, sau đó bỏ cột area.
     */
    public function up(): void
    {
        Schema::table('dining_table', function (Blueprint $table) {
            $table->foreignId('zone_id')->nullable()->after('name')->constrained('table_zones');
        });

        // Ánh xạ area cũ -> zone mới (chỉ tạo zone khi còn bàn đang dùng area đó).
        $zoneNamesByArea = [
            'indoor' => 'Trong nhà',
            'outdoor' => 'Ngoài trời',
        ];

        foreach ($zoneNamesByArea as $area => $zoneName) {
            $hasArea = DiningTable::query()->where('area', $area)->exists();

            if (! $hasArea) {
                continue;
            }

            $zone = TableZone::query()->firstOrCreate(['name' => $zoneName], ['is_active' => true]);

            DiningTable::query()
                ->where('area', $area)
                ->update(['zone_id' => $zone->id]);
        }

        Schema::table('dining_table', function (Blueprint $table) {
            $table->dropColumn('area');
        });
    }

    public function down(): void
    {
        Schema::table('dining_table', function (Blueprint $table) {
            $table->string('area', 20)->nullable()->after('name');
        });

        // Đảo ngược: trả area theo zone đã ánh xạ (nếu còn tồn tại).
        $areaByZoneName = [
            'Trong nhà' => 'indoor',
            'Ngoài trời' => 'outdoor',
        ];

        foreach ($areaByZoneName as $zoneName => $area) {
            $zone = TableZone::query()->where('name', $zoneName)->first();

            if ($zone === null) {
                continue;
            }

            DiningTable::query()
                ->where('zone_id', $zone->id)
                ->update(['area' => $area]);
        }

        Schema::table('dining_table', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zone_id');
        });
    }
};
