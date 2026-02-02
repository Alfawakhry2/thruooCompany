<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();

            // Team Info
            $table->string('name'); // Team name
            $table->text('description')->nullable();

            // Team Leadership
            $table->foreignId('team_lead_id')->constrained('users')->onDelete('cascade'); // Team leader

            // Module Association
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');

            // Who Created
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            // Status
            $table->enum('status', ['active', 'inactive'])->default('active');

            // Team Settings
            $table->json('settings')->nullable(); // For future flexibility

            $table->timestamps();

            // Indexes
            $table->index('team_lead_id');
            $table->index('module_id');
            $table->index('created_by');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('teams');
    }
};
