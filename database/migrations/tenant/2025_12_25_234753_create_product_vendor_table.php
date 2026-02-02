<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_vendor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('vendor_id')->constrained('vendors')->onDelete('cascade');

            // Vendor-specific pricing
            $table->decimal('vendor_price', 15, 2)->nullable(); // Price from this vendor
            $table->boolean('is_primary')->default(false); // Primary vendor for this product

            $table->timestamps();
            $table->softDeletes();

            // Unique constraint
            $table->unique(['product_id', 'vendor_id']);

            // Indexes
            $table->index('product_id');
            $table->index('vendor_id');
            $table->index('is_primary');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_vendor');
    }
};
