<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ⚠️ NEW FILE - Create this file, don't replace existing files
 * Location: database/migrations/landlord/2026_01_01_100001_create_companies_table.php
 *
 * NEW ARCHITECTURE:
 * - Each company has its own database
 * - One owner_email can have multiple companies
 * - Subdomain auto-generated from company name
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Owner Info (One email can own multiple companies)
            $table->string('owner_email')->index(); // Can have multiple companies
            $table->string('owner_name');
            $table->string('owner_phone')->nullable();

            // Company Basic Info
            $table->string('name'); // e.g., "Ahmed Tech"
            $table->string('subdomain')->unique(); // e.g., "ahmed-tech" (auto-generated)
            $table->string('domain')->nullable()->unique(); // Custom domain (optional)
            $table->string('database')->unique(); // e.g., "company_ahmed_tech" - Each company = separate DB

            // Company Contact Info
            $table->string('email')->unique(); // Company email
            $table->string('phone')->nullable();
            $table->string('business_email')->nullable();

            // Company Details
            $table->string('industry')->nullable();
            $table->string('staff_count')->nullable();
            $table->string('website')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();

            // Legal Info
            $table->string('legal_id')->nullable();
            $table->string('tax_id')->nullable();

            // Branding
            $table->string('logo')->nullable();

            // Subscription & Status (per company)
            $table->enum('status', ['pending', 'active', 'suspended', 'cancelled'])->default('active');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->string('plan')->default('trial'); // trial, starter, professional, enterprise
            $table->json('enabled_modules')->nullable(); // ["sales", "contacts", "accounting"]

            // Settings & Meta
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index(['owner_email', 'status']); // Find all companies for an owner
            $table->index('status');
            $table->index('plan');
            $table->index('subdomain');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('companies');
    }
};
