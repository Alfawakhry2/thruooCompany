<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('category_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->timestamps();

            // Unique constraint
            $table->unique(['category_id', 'team_id']);

            // Indexes
            $table->index('category_id');
            $table->index('team_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('category_team');
    }
};
