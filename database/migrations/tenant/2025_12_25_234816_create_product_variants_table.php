<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');

            // Variant Details
            $table->string('name')->nullable(); // e.g., "Large - Red"
            $table->string('name_ar')->nullable();
            $table->string('sku')->unique()->nullable();

            // Pricing (overrides product base price)
            $table->decimal('price', 15, 2);
            $table->decimal('cost_price', 15, 2)->nullable();

            // Stock
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('reserved')->default(0);

            // Media
            $table->string('image')->nullable();

            // Status
            $table->boolean('status')->default(true);

            // Additional
            $table->json('attributes')->nullable(); // {color: "red", size: "L"}

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('product_id');
            $table->index('sku');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_variants');
    }
};
