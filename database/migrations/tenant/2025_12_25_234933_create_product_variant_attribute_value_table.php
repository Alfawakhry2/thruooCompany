<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_variant_attribute_value', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('attribute_value_id')->constrained('attribute_values')->onDelete('cascade');
            $table->timestamps();

            // Unique constraint - each attribute value can only be used once per variant
            $table->unique(['product_variant_id', 'attribute_value_id'], 'variant_attribute_unique');

            // Indexes
            $table->index('product_variant_id');
            $table->index('attribute_value_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_variant_attribute_value');
    }
};
