<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add slug column to companies table for path-based routing
 * 
 * Migration: 2026_01_13_000001_add_slug_to_companies_table.php
 * Location: database/migrations/landlord/
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('mysql')->table('companies', function (Blueprint $table) {
            // Add slug column after subdomain
            $table->string('slug')->nullable()->after('subdomain');
            
            // Add unique index on slug
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->table('companies', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
