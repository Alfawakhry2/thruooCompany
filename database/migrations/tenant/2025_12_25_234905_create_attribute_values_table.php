<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained('attributes')->onDelete('cascade');

            // Value Info
            $table->string('value'); // e.g., "Red", "Large"
            $table->string('value_ar')->nullable();

            // Additional
            $table->string('color_code')->nullable(); // For color attributes: #FF0000

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('attribute_id');
            $table->index('is_active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('attribute_values');
    }
};
