<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();

            // Vendor Info
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Company Details
            $table->string('company_name')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_number')->nullable();

            // Contact Person
            $table->string('contact_person')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();

            // Module Association
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');

            // Who Created
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            // Status
            $table->enum('status', ['active', 'inactive'])->default('active');

            // Additional Info
            $table->text('notes')->nullable();
            $table->json('settings')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('module_id');
            $table->index('created_by');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('vendors');
    }
};
