<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();

            // Attribute Info
            $table->string('name'); // e.g., "Color", "Size"
            $table->string('name_ar')->nullable();

            // Module Association
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');

            // Who Created
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('module_id');
            $table->index('is_active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('attributes');
    }
};
