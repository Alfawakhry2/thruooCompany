<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Populate slug column for existing companies
 * Uses subdomain as base for slug generation
 * 
 * Migration: 2026_01_13_000002_populate_company_slugs.php
 * Location: database/migrations/landlord/
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get all companies that don't have a slug yet
        $companies = DB::connection('mysql')
            ->table('companies')
            ->whereNull('slug')
            ->get();

        foreach ($companies as $company) {
            // Generate slug from subdomain (already unique and clean)
            // Subdomain format is already lowercase with hyphens
            $slug = $company->subdomain;
            
            // Ensure uniqueness (in case of conflicts)
            $originalSlug = $slug;
            $counter = 1;
            
            while (DB::connection('mysql')
                ->table('companies')
                ->where('slug', $slug)
                ->where('id', '!=', $company->id)
                ->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            
            // Update the company with the slug
            DB::connection('mysql')
                ->table('companies')
                ->where('id', $company->id)
                ->update([
                    'slug' => $slug,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Set all slugs to null
        DB::connection('mysql')
            ->table('companies')
            ->update(['slug' => null]);
    }
};
