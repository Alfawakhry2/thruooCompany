<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            // Basic Lead Info
            $table->string('name'); // Client Name
            $table->string('email')->nullable();
            $table->string('phone'); // Mobile Number
            $table->string('position')->nullable(); // CEO, Manager, etc.

            // Company Info
            $table->string('company')->nullable();
            $table->string('company_phone')->nullable();
            $table->string('company_email')->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();

            // Lead Details
            $table->string('ask')->nullable(); // What client is asking for
            $table->text('service')->nullable(); // Commerce Mobile Application, etc.
            $table->text('description')->nullable(); // Lead description/notes
            $table->decimal('value', 15, 2)->nullable(); // EGP 10,000

            // Campaign & Source
            $table->string('campaign_id')->nullable(); // Campaign ID
            $table->foreignId('source_id')->nullable()->constrained('lead_sources')->onDelete('set null'); // What's App, Web, etc.

            // Status & Stage
            $table->foreignId('status_id')->nullable()->constrained('lead_statuses')->onDelete('set null'); // Stage: In Progress, New, etc.
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');

            // Assignment
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null'); // Sales Name
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            // Module
            $table->foreignId('module_id')->nullable()->constrained('modules')->onDelete('set null');

            // Social Media Links
            $table->string('instagram')->nullable();
            $table->string('facebook')->nullable();
            $table->string('tiktok')->nullable();
            $table->string('snapchat')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('youtube')->nullable();

            // Conversion
            $table->boolean('is_converted')->default(false);
            $table->timestamp('converted_at')->nullable();

            // Tracking
            $table->timestamp('first_contact_at')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamp('next_followup_at')->nullable();

            // Custom Fields (for flexibility)
            $table->json('custom_fields')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status_id');
            $table->index('source_id');
            $table->index('assigned_to');
            $table->index('created_by');
            $table->index('module_id');
            $table->index('created_at');
            $table->index('is_converted');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
