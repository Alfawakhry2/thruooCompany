<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations
     * Updates tenants table to be owner/account table
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Remove columns that moved to companies
            $table->dropColumn([
                'subdomain',
                'domain',
                'database',
                'business_email',
                'industry',
                'staff_count',
                'website',
                'legal_id',
                'tax_id',
                'logo',
                'status',
                'trial_ends_at',
                'subscription_ends_at',
                'plan',
                'enabled_modules',
            ]);
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('subdomain')->unique()->nullable();
            $table->string('domain')->unique()->nullable();
            $table->string('database')->unique()->nullable();
            $table->string('business_email')->nullable();
            $table->string('industry')->nullable();
            $table->string('staff_count')->nullable();
            $table->string('website')->nullable();
            $table->string('legal_id')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('logo')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->string('plan')->default('trial');
            $table->json('enabled_modules')->nullable();
        });
    }
};
