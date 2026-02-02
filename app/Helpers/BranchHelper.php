<?php

if (!function_exists('currentBranch')) {
    /**
     * Get the current branch from request context
     */
    function currentBranch(): ?\App\Models\Modules\Sales\Branch
    {
        return app('currentBranch');
    }
}

if (!function_exists('currentBranchId')) {
    /**
     * Get the current branch ID from request context
     */
    function currentBranchId(): ?int
    {
        return app('currentBranchId');
    }
}

if (!function_exists('setCurrentBranch')) {
    /**
     * Set the current branch in request context
     */
    function setCurrentBranch(\App\Models\Modules\Sales\Branch $branch): void
    {
        app()->instance('currentBranch', $branch);
        app()->instance('currentBranchId', $branch->id);
    }
}

if (!function_exists('userCanAccessBranch')) {
    /**
     * Check if current user can access a branch
     */
    function userCanAccessBranch(int $branchId): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }
        
        // Super Admin / Admin can access all branches
        if ($user->isOwner() || $user->hasRole('Super Admin')) {
            return true;
        }
        
        // Check if user belongs to branch
        return $user->belongsToBranch($branchId);
    }
}