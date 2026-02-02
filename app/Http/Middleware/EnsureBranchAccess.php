<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Modules\Sales\Branch;

class EnsureBranchAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $branchId = $request->route('branchId');
        
        if (!$branchId) {
            return response()->json([
                'success' => false,
                'message' => 'Branch ID is required in the URL path.',
            ], 400);
        }
        
        // Find branch
        $branch = Branch::find($branchId);
        
        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found.',
            ], 404);
        }
        
        // Check if branch is active
        if (!$branch->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Branch is not active.',
            ], 403);
        }
        
        // Check if user has access to this branch
        if (!userCanAccessBranch($branchId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this branch.',
            ], 403);
        }
        
        // Set current branch in context
        setCurrentBranch($branch);
        
        // Store in request for controllers
        $request->merge(['current_branch' => $branch]);
        
        return $next($request);
    }
}