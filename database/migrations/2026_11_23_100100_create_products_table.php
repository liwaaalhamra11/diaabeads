<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 32)->unique();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->longText('description')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('initial_price', 10, 2)->default(0);   // cost price
            $table->decimal('selling_price', 10, 2);
            $table->decimal('discount_price', 10, 2)->nullable();  // sale price, optional
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'deleted_at']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
    }
};
