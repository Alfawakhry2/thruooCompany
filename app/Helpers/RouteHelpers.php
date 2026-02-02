<?php

/**
 * Route Helper Functions for Path-Based Multi-Tenancy
 * 
 * These functions help generate URLs with company slug prefix
 */

if (!function_exists('companyRoute')) {
    /**
     * Generate a company-specific route URL
     * 
     * @param string $path The path after company slug (e.g., 'api/auth/me')
     * @param array $params Query parameters
     * @return string Full URL with company slug
     * 
     * @example companyRoute('api/modules/1/branches/1/leads') 
     *          → http://thruoo.local/ahmed/api/modules/1/branches/1/leads
     */
    function companyRoute(string $path, array $params = []): string
    {
        $company = app('currentCompany');
        
        if (!$company) {
            throw new \Exception('No current company context. Cannot generate company route.');
        }
        
        $path = ltrim($path, '/');
        return url("/{$company->slug}/{$path}", $params);
    }
}

if (!function_exists('branchRoute')) {
    /**
     * Generate a branch-specific route URL
     * 
     * @param string|int $moduleId Module ID
     * @param string|int $branchId Branch ID
     * @param string $path The path after branch (e.g., 'leads')
     * @param array $params Query parameters
     * @return string Full URL with company slug and branch path
     * 
     * @example branchRoute(1, 1, 'leads') 
     *          → http://thruoo.local/ahmed/api/modules/1/branches/1/leads
     */
    function branchRoute($moduleId, $branchId, string $path, array $params = []): string
    {
        $company = app('currentCompany');
        
        if (!$company) {
            throw new \Exception('No current company context. Cannot generate branch route.');
        }
        
        $path = ltrim($path, '/');
        return url("/{$company->slug}/api/modules/{$moduleId}/branches/{$branchId}/{$path}", $params);
    }
}

if (!function_exists('validateSlug')) {
    /**
     * Validate slug format
     * 
     * Slug must be lowercase, alphanumeric with hyphens only
     * Pattern: ^[a-z0-9]+(?:-[a-z0-9]+)*$
     * 
     * @param string $slug The slug to validate
     * @return bool True if valid, false otherwise
     * 
     * @example validateSlug('ahmed-tech') → true
     * @example validateSlug('Ahmed-Tech') → false (uppercase)
     * @example validateSlug('ahmed_tech') → false (underscore)
     */
    function validateSlug(string $slug): bool
    {
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
    }
}

if (!function_exists('currentCompanySlug')) {
    /**
     * Get the current company slug
     * 
     * @return string|null Company slug or null if no company context
     */
    function currentCompanySlug(): ?string
    {
        $company = app('currentCompany');
        return $company?->slug;
    }
}

if (!function_exists('currentBranchId')) {
    /**
     * Get the current branch ID from request
     * 
     * @return int|null Branch ID or null if not in branch context
     */
    function currentBranchId(): ?int
    {
        return request()->get('current_branch_id');
    }
}
