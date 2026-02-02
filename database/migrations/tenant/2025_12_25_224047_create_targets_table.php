<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('targets', function (Blueprint $table) {
            $table->id();
            // Target Details
            $table->enum('target_type', ['monthly', 'quarterly', 'yearly', 'custom'])->default('monthly');
            $table->decimal('target_value', 15, 2); // Target amount in currency
            $table->string('target_name')->nullable(); // e.g., "Q1 2025 Sales Target"
            $table->text('description')->nullable();

            // Assignment
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade'); // Specific user target
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('cascade');
            $table->string('role_name')->nullable(); // Target for all users with this role (e.g., "Sales")

            // Time Period
            $table->date('start_date');
            $table->date('end_date');

            // Module Association
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');

            // Who Created
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            // Status
            $table->enum('status', ['active', 'completed', 'expired'])->default('active');

            // Progress Tracking
            $table->decimal('achieved_value', 15, 2)->default(0); // Auto-calculated
            $table->decimal('progress_percentage', 5, 2)->default(0); // Auto-calculated

            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('team_id');
            $table->index('role_name');
            $table->index('module_id');
            $table->index('created_by');
            $table->index(['start_date', 'end_date']);
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('targets');
    }
};
