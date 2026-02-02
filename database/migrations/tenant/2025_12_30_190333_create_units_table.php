<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('category_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->constrained('users');

            // Basic info
            $table->string('title');
            $table->string('address')->nullable();

            // Location
            $table->string('location')->nullable(); // lat,long
            $table->string('city')->nullable();
            $table->string('area')->nullable();

            // Types
            $table->enum('property_type', ['residential', 'commercial']);
            $table->enum('listing_type', ['renting', 'selling']);
            $table->enum('unit_type', [
                'apartment',
                'villa',
                'duplex',
                'shale',
                'cabin',
                'office',
                'mall',
                'building'
            ]);

            // Size & price
            $table->decimal('size', 10, 2)->nullable();
            $table->decimal('price', 15, 2)->nullable();

            // Description
            $table->text('description')->nullable();

            // Media
            $table->string('image_path')->nullable();
            $table->string('document_path')->nullable();

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
