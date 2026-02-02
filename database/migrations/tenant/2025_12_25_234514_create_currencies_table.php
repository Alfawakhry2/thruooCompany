<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();

            // Currency Info
            $table->string('name'); // e.g., "US Dollar", "Egyptian Pound"
            $table->string('name_ar')->nullable();
            $table->string('code', 3)->unique(); // ISO 4217: USD, EGP, EUR
            $table->string('symbol'); // $, £, €, ج.م

            // Exchange Rate (relative to base currency)
            $table->decimal('exchange_rate', 15, 8)->default(1.00000000);

            // Module Association
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_base')->default(false); // Base currency for tenant

            // Who Created
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('module_id');
            $table->index('code');
            $table->index('is_active');
            $table->index('is_base');
        });
    }

    public function down()
    {
        Schema::dropIfExists('currencies');
    }
};
