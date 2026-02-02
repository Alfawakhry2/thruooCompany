<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->timestamps();

            // Unique constraint - user can only be in one team per module
            $table->unique(['team_id', 'user_id', 'module_id']);

            // Indexes
            $table->index('team_id');
            $table->index('user_id');
            $table->index('module_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('team_members');
    }
};
