<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('store', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Tên chi nhánh');
            $table->text('address')->nullable()->comment('Địa chỉ chi nhánh');
            $table->string('phone', 20)->nullable()->comment('Số điện thoại');
            $table->string('email')->nullable()->comment('Email liên hệ');
            $table->string('opening_hours')->nullable()->comment('Giờ mở cửa');
            $table->boolean('is_active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store');
    }
};
