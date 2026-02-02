<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Product Info (Bilingual)
            $table->string('title'); // English title
            $table->string('title_ar'); // Arabic title
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();

            // SKU & Barcode
            $table->string('sku')->unique()->nullable();
            $table->string('barcode')->nullable();

            // Pricing
            $table->decimal('base_price', 15, 2); // Base/original price
            $table->decimal('discount_price', 15, 2)->nullable(); // Sale price
            $table->decimal('cost_price', 15, 2)->nullable(); // Cost from vendor

            // Stock Management
            $table->unsignedInteger('base_stock')->default(0); // Total available stock
            $table->unsignedInteger('reserved')->default(0); // Reserved in orders
            $table->unsignedInteger('min_stock')->default(0); // Minimum stock alert

            // Branch/Location Management
            $table->boolean('track_by_branch')->default(false);
            $table->timestamp('last_restocked_at')->nullable();
            $table->unsignedBigInteger('total_stock_lifetime')->default(0);

            // Tax & Currency
            $table->foreignId('tax_id')->nullable()->constrained('taxes')->onDelete('set null');
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->onDelete('set null');

            // Module Association
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');

            // Default Branch (optional - where product primarily belongs)
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');

            // Who Created
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            // Media
            $table->string('image')->nullable(); // Main product image
            $table->json('images')->nullable(); // Additional images array

            // Status
            $table->boolean('status')->default(true); // Active/Inactive
            $table->boolean('is_featured')->default(false);
            $table->boolean('track_stock')->default(true);

            // SEO & Metadata
            $table->string('slug')->unique()->nullable();
            $table->json('meta')->nullable(); // SEO metadata

            // Additional Settings
            $table->json('settings')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('module_id');
            $table->index('created_by');
            $table->index('branch_id');
            $table->index('status');
            $table->index('sku');
            $table->index('slug');
            $table->index('tax_id');
            $table->index('currency_id');
            $table->index('track_by_branch');
            $table->index('last_restocked_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};
