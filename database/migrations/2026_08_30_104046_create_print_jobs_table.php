<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng print_jobs: lịch sử/lệnh in gửi tới máy in; các lệnh in lỗi
     * giữ lại kèm payload snapshot để in lại sau.
     */
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('printer_id')->constrained('printers');
            $table->foreignId('order_id')->nullable()->constrained('orders');
            $table->string('print_type', 50)->nullable()->comment('Loại nội dung in: kitchen, receipt, ...');
            $table->string('status', 50)->default('pending')->comment('pending / printing / success / failed / canceled');
            $table->integer('attempts')->default(0);
            $table->text('error_message')->nullable()->comment('Lỗi khi in thất bại để xử lý in lại');
            $table->json('payload')->nullable()->comment('Snapshot nội dung cần in (đề phòng dữ liệu gốc thay đổi)');
            $table->timestamps();
            $table->timestamp('printed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
