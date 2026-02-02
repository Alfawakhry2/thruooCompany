<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            $table->string('type');
            // Bilingual Names
            $table->string('name'); // English name
            $table->string('name_ar'); // Arabic name

            // Details
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();

            // Image
            $table->string('image')->nullable();

            // Parent Category (for subcategories)
            $table->foreignId('parent_id')->nullable()->constrained('categories')->onDelete('cascade');

            // Module Association
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');

            // Who Created
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            // Status
            $table->enum('status', ['active', 'inactive'])->default('active');

            // Ordering
            $table->integer('order')->default(0);

            // Settings
            $table->json('settings')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('module_id');
            $table->index('parent_id');
            $table->index('created_by');
            $table->index('status');
            $table->index('order');
        });
    }

    public function down()
    {
        Schema::dropIfExists('categories');
    }
};
