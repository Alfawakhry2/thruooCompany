<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Basic Info
            $table->string('name');                              // Company name
            $table->string('subdomain')->unique();               // Auto-generated from company name
            $table->string('domain')->nullable();                // Custom domain (optional)
            $table->string('database')->unique();                // Database name: tenant_{subdomain}

            // Contact Info
            $table->string('email')->unique();                   // Primary admin email
            $table->string('phone')->nullable();                 // Company phone
            $table->string('business_email')->nullable();        // Company business email

            // Company Details
            $table->string('industry')->nullable();              // Technology, Healthcare, etc.
            $table->string('staff_count')->nullable();           // 1-10, 11-50, etc.
            $table->string('website')->nullable();               // Company website
            $table->string('country')->nullable();               // Country
            $table->string('city')->nullable();                  // City
            $table->string('address')->nullable();               // Full address (optional)

            // Legal Info
            $table->string('legal_id')->nullable();              // Company registration number
            $table->string('tax_id')->nullable();                // Tax ID / VAT number

            // Branding
            $table->string('logo')->nullable();                  // Logo path or URL

            // Referral Info
            $table->string('referral_code')->nullable();         // Referral code used
            $table->string('referral_relation')->nullable();     // How they know the referrer

            // Subscription & Status
            $table->enum('status', ['pending', 'active', 'suspended', 'cancelled'])->default('pending');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->string('plan')->default('trial');            // trial, starter, professional, enterprise
            $table->json('enabled_modules')->nullable();         // ["sales", "contacts", "accounting"]

            // Settings & Meta
            $table->json('settings')->nullable();                // Additional settings
            $table->json('metadata')->nullable();                // Any extra data

            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('plan');
            $table->index('industry');
            $table->index('country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
