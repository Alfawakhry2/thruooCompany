<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations
     * Creates company_details table for better company management
     */
    public function up(): void
    {
        Schema::create('company_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->onDelete('cascade');

            // Additional Company Information
            $table->text('description')->nullable();
            $table->string('founded_year')->nullable();
            $table->string('employee_count')->nullable();

            // Financial Information
            $table->string('annual_revenue')->nullable();
            $table->string('currency')->default('USD');

            // Social Media Links
            $table->string('facebook')->nullable();
            $table->string('instagram')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('twitter')->nullable();
            $table->string('youtube')->nullable();
            $table->string('tiktok')->nullable();
            $table->string('snapchat')->nullable();
            $table->string('whatsapp')->nullable();

            // Additional Contact Information
            $table->string('secondary_email')->nullable();
            $table->string('secondary_phone')->nullable();
            $table->string('fax')->nullable();

            // Business Hours
            $table->json('business_hours')->nullable(); // Store business hours as JSON

            // Additional Settings
            $table->json('additional_settings')->nullable();

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('company_id');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('company_details');
    }
};

