<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();

            // Tax Info
            $table->string('name'); // e.g., "VAT", "Sales Tax"
            $table->string('name_ar')->nullable();
            $table->decimal('rate', 5, 2); // e.g., 14.00 for 14%

            // Module Association
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');

            // Who Created
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false); // Default tax for new products

            // Additional Info
            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('module_id');
            $table->index('created_by');
            $table->index('is_active');
            $table->index('is_default');
        });
    }

    public function down()
    {
        Schema::dropIfExists('taxes');
    }
};
