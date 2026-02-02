<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\StoreTeamRequest;
use App\Http\Requests\Api\Sales\UpdateTeamRequest;
use App\Models\Modules\Sales\Team;
use App\Models\Modules\Module;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TeamController extends Controller
{
    /**
     * Get all teams for a module
     */
    public function index($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $perPage = $request->query('per_page', 15);
        $status = $request->query('status');
        $teamLeadId = $request->query('team_lead_id');

        $query = Team::with(['leader', 'creator', 'module', 'members'])
            ->forBranch($branchId)
            ->where('module_id', $moduleId);

        // Filter by status
        if ($status) {
            $query->where('status', $status);
        }

        // Filter by team leader
        if ($teamLeadId) {
            $query->where('team_lead_id', $teamLeadId);
        }

        $teams = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $teams,
        ]);
    }

    /**
     * Create a new team
     */
    public function store($companySlug, $moduleId, $branchId, StoreTeamRequest $request): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $data = $request->validated();
        $data['module_id'] = $moduleId;
        $data['branch_id'] = $branchId;
        $data['created_by'] = Auth::id();
        $data['status'] = $data['status'] ?? 'active';

        $team = Team::create($data);

        // Add team members if provided
        if (!empty($data['member_ids'])) {
            foreach ($data['member_ids'] as $memberId) {
                $team->addMember($memberId);
            }
        }

        // Automatically add team leader as member
        if (!in_array($data['team_lead_id'], $data['member_ids'] ?? [])) {
            $team->addMember($data['team_lead_id']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Team created successfully',
            'data' => $team->load(['leader', 'creator', 'module', 'members']),
        ], 201);
    }

    /**
     * Get a specific team
     */
    public function show($companySlug, $moduleId, $branchId, $teamId): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $team = Team::with(['leader', 'creator', 'module', 'members', 'activeTargets'])
            ->forBranch($branchId)
            ->where('module_id', $moduleId)
            ->find($teamId);

        if (!$team) {
            return response()->json([
                'success' => false,
                'message' => 'Team not found',
            ], 404);
        }

        // Add performance data
        $team->current_month_performance = $team->getCurrentMonthPerformance();

        return response()->json([
            'success' => true,
            'data' => $team,
        ]);
    }

    /**
     * Update a team
     */
    public function update($companySlug, $moduleId, $branchId, UpdateTeamRequest $request, $teamId): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $team = Team::forBranch($branchId)->where('module_id', $moduleId)->find($teamId);

        if (!$team) {
            return response()->json([
                'success' => false,
                'message' => 'Team not found',
            ], 404);
        }

        $team->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Team updated successfully',
            'data' => $team->fresh(['leader', 'creator', 'module', 'members']),
        ]);
    }

    /**
     * Delete a team
     */
    public function destroy($companySlug, $moduleId, $branchId, $teamId): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();

        // Only owner/admin can delete
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete teams',
            ], 403);
        }

        $team = Team::forBranch($branchId)->where('module_id', $moduleId)->find($teamId);

        if (!$team) {
            return response()->json([
                'success' => false,
                'message' => 'Team not found',
            ], 404);
        }

        $team->delete();

        return response()->json([
            'success' => true,
            'message' => 'Team deleted successfully',
        ]);
    }

    /**
     * Add member to team
     */
    public function addMember($companySlug, $moduleId, $branchId, $teamId, Request $request): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();

        // Only owner/admin can add members
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to add team members',
            ], 403);
        }

        $team = Team::forBranch($branchId)->where('module_id', $moduleId)->find($teamId);

        if (!$team) {
            return response()->json([
                'success' => false,
                'message' => 'Team not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'exists:users,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $added = $team->addMember($request->user_id);

        if (!$added) {
            return response()->json([
                'success' => false,
                'message' => 'User is already a team member',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Member added successfully',
            'data' => $team->fresh(['members']),
        ]);
    }

    /**
     * Remove member from team
     */
    public function removeMember($companySlug, $moduleId, $branchId, $teamId, $userId): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();

        // Only owner/admin can remove members
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to remove team members',
            ], 403);
        }

        $team = Team::forBranch($branchId)->where('module_id', $moduleId)->find($teamId);

        if (!$team) {
            return response()->json([
                'success' => false,
                'message' => 'Team not found',
            ], 404);
        }

        // Don't allow removing team leader
        if ($team->team_lead_id == $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove team leader from team',
            ], 400);
        }

        $removed = $team->removeMember($userId);

        if (!$removed) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a team member',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Member removed successfully',
            'data' => $team->fresh(['members']),
        ]);
    }

    /**
     * Get team performance/stats
     */
    public function performance($companySlug, $moduleId, $branchId, $teamId, Request $request): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $team = Team::forBranch($branchId)->where('module_id', $moduleId)->find($teamId);

        if (!$team) {
            return response()->json([
                'success' => false,
                'message' => 'Team not found',
            ], 404);
        }

        $period = $request->query('period', 'current_month'); // current_month, last_month, current_year, custom

        switch ($period) {
            case 'current_month':
                $startDate = now()->startOfMonth();
                $endDate = now()->endOfMonth();
                break;
            case 'last_month':
                $startDate = now()->subMonth()->startOfMonth();
                $endDate = now()->subMonth()->endOfMonth();
                break;
            case 'current_year':
                $startDate = now()->startOfYear();
                $endDate = now()->endOfYear();
                break;
            case 'custom':
                $startDate = $request->query('start_date') ? \Carbon\Carbon::parse($request->query('start_date')) : now()->startOfMonth();
                $endDate = $request->query('end_date') ? \Carbon\Carbon::parse($request->query('end_date')) : now()->endOfMonth();
                break;
            default:
                $startDate = now()->startOfMonth();
                $endDate = now()->endOfMonth();
        }

        $convertedValue = $team->getTotalConvertedValue($startDate, $endDate);
        $convertedCount = $team->getTotalConvertedLeads($startDate, $endDate);

        // Get active targets for this period
        $activeTargets = $team->targets()
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->get();

        // Update progress for each target
        foreach ($activeTargets as $target) {
            $target->updateProgress();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                ],
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'member_count' => $team->members()->count(),
                ],
                'performance' => [
                    'converted_value' => round($convertedValue, 2),
                    'converted_count' => $convertedCount,
                ],
                'targets' => $activeTargets->fresh(),
            ],
        ]);
    }

    /**
     * Get teams where current user is a member
     */
    public function myTeams($companySlug, $moduleId, $branchId): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();

        $teams = Team::with(['leader', 'creator', 'module', 'members'])
            ->forBranch($branchId)
            ->where('module_id', $moduleId)
            ->whereHas('members', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->orWhere('team_lead_id', $user->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $teams,
        ]);
    }
}
