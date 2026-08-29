<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->foreignId('product_group_id')->nullable()->constrained('product_group');
            $table->boolean('is_active')->default(true);
            $table->string('image_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product');
    }
};
