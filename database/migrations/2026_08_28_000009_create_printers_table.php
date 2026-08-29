<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained('store');
            $table->string('name');
            $table->string('printer_type', 50)->nullable()->comment('Ví dụ: receipt, kitchen, label');
            $table->string('ip_address', 45)->nullable()->comment('Địa chỉ IP máy in');
            $table->integer('port')->nullable()->comment('Cổng kết nối');
            $table->boolean('is_active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};
