<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations
     * Updates companies table structure
     */
    public function up(): void
    {
        // If table doesn't exist, create it
        if (!Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table) {
                $table->uuid('id')->primary();

                // Relationships
                $table->foreignUuid('tenant_id')->constrained('tenants')->onDelete('cascade');

                // Basic Info
                $table->string('name');
                $table->string('subdomain')->unique();
                $table->string('domain')->unique()->nullable();
                $table->string('database')->unique();

                // Contact Info
                $table->string('business_email');
                $table->string('phone')->nullable();

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

                // Subscription & Status
                $table->string('status')->default('active');
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('subscription_ends_at')->nullable();
                $table->string('plan')->default('trial');
                $table->json('enabled_modules')->nullable();

                // Settings & Meta
                $table->json('settings')->nullable();
                $table->json('metadata')->nullable();

                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index('tenant_id');
                $table->index('status');
                $table->index('subdomain');
            });
        } else {
            // Table exists, just add missing columns
            Schema::table('companies', function (Blueprint $table) {
                if (!Schema::hasColumn('companies', 'tenant_id')) {
                    $table->foreignUuid('tenant_id')->after('id')->constrained('tenants')->onDelete('cascade');
                }

                if (!Schema::hasColumn('companies', 'subdomain')) {
                    $table->string('subdomain')->unique()->after('name');
                }

                if (!Schema::hasColumn('companies', 'database')) {
                    $table->string('database')->unique()->after('domain');
                }
            });
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
