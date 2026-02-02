<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use App\Http\Controllers\Controller;
use App\Models\Modules\Sales\Lead;
use App\Models\Modules\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LeadController extends Controller
{
    /**
     * Get all leads with filters and search
     */
    public function index($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $perPage = $request->query('per_page', 15);
        $search = $request->query('search');
        $statusId = $request->query('status_id');
        $sourceId = $request->query('source_id');
        $assignedTo = $request->query('assigned_to');
        $priority = $request->query('priority');
        $isConverted = $request->query('is_converted');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $user = Auth::user();

        $query = Lead::with(['source', 'status', 'assignedUser', 'creator', 'module', 'branch'])
            ->where('module_id', $moduleId)
            ->where('branch_id', $branchId);

        // If not owner/admin, only show leads assigned to user or created by user
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                    ->orWhere('created_by', $user->id);
            });
        }

        // Apply filters
        if ($search) {
            $query->search($search);
        }

        if ($statusId) {
            $query->where('status_id', $statusId);
        }

        if ($sourceId) {
            $query->where('source_id', $sourceId);
        }

        if ($assignedTo) {
            if ($assignedTo === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $assignedTo);
            }
        }

        if ($priority) {
            $query->where('priority', $priority);
        }

        if ($isConverted !== null) {
            $query->where('is_converted', filter_var($isConverted, FILTER_VALIDATE_BOOLEAN));
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $leads = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $leads,
        ]);
    }

    /**
     * Create a new lead
     */
    public function store($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            // Basic Info
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'position' => ['nullable', 'string', 'max:100'],

            // Company Info
            'company' => ['nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:20'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string'],

            // Lead Details
            'ask' => ['nullable', 'string', 'max:500'],
            'service' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'value' => ['nullable', 'numeric', 'min:0'],

            // Campaign & Source
            'campaign_id' => ['nullable', 'string', 'max:100'],
            'source_id' => ['required', 'exists:lead_sources,id'],

            // Status & Stage
            'status_id' => ['required', 'exists:lead_statuses,id'],
            'priority' => ['nullable', 'in:low,medium,high'],

            // Assignment
            'assigned_to' => ['nullable', 'exists:users,id'],

            // Social Media
            'instagram' => ['nullable', 'string', 'max:255'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'tiktok' => ['nullable', 'string', 'max:255'],
            'snapchat' => ['nullable', 'string', 'max:255'],
            'linkedin' => ['nullable', 'string', 'max:255'],
            'youtube' => ['nullable', 'string', 'max:255'],

            // Custom
            'custom_fields' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['created_by'] = Auth::id();
        $data['module_id'] = $moduleId;
        $data['branch_id'] = $branchId;
        $data['priority'] = $data['priority'] ?? 'medium';

        $lead = Lead::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Lead created successfully',
            'data' => $lead->load(['source', 'status', 'assignedUser', 'creator', 'module', 'branch']),
        ], 201);
    }

    /**
     * Get a specific lead (Profile View)
     */
    public function show($companySlug, $moduleId, $branchId, $leadId): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();

        $lead = Lead::with([
            'source',
            'status',
            'assignedUser',
            'creator',
            'module',
            'contracts',
            'activityLogs' => function ($query) {
                $query->latest()->limit(10);
            }
        ])
            ->where('module_id', $moduleId)
            ->where('branch_id', $branchId)
            ->find($leadId);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found',
            ], 404);
        }

        // Check permission
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            if ($lead->assigned_to !== $user->id && $lead->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view this lead',
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $lead,
        ]);
    }

    /**
     * Update a lead
     */
    public function update($companySlug, $moduleId, $branchId, Request $request, $leadId): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();

        $lead = Lead::where('module_id', $moduleId)
            ->where('branch_id', $branchId)
            ->find($leadId);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found',
            ], 404);
        }

        // Check permission
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            if ($lead->assigned_to !== $user->id && $lead->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to update this lead',
                ], 403);
            }
        }

        $validator = Validator::make($request->all(), [
            // Basic Info
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:20'],
            'position' => ['nullable', 'string', 'max:100'],

            // Company Info
            'company' => ['nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:20'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string'],

            // Lead Details
            'ask' => ['nullable', 'string', 'max:500'],
            'service' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'value' => ['nullable', 'numeric', 'min:0'],

            // Campaign & Source
            'campaign_id' => ['nullable', 'string', 'max:100'],
            'source_id' => ['sometimes', 'required', 'exists:lead_sources,id'],

            // Status & Stage
            'status_id' => ['sometimes', 'required', 'exists:lead_statuses,id'],
            'priority' => ['nullable', 'in:low,medium,high'],

            // Assignment
            'assigned_to' => ['nullable', 'exists:users,id'],

            // Social Media
            'instagram' => ['nullable', 'string', 'max:255'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'tiktok' => ['nullable', 'string', 'max:255'],
            'snapchat' => ['nullable', 'string', 'max:255'],
            'linkedin' => ['nullable', 'string', 'max:255'],
            'youtube' => ['nullable', 'string', 'max:255'],

            // Custom
            'custom_fields' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $lead->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Lead updated successfully',
            'data' => $lead->fresh(['source', 'status', 'assignedUser', 'creator', 'module']),
        ]);
    }

    /**
     * Delete a lead
     */
    public function destroy($companySlug, $moduleId, $branchId, $leadId): JsonResponse
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
                'message' => 'You do not have permission to delete leads',
            ], 403);
        }

        $lead = Lead::where('module_id', $moduleId)
            ->where('branch_id', $branchId)
            ->find($leadId);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found',
            ], 404);
        }

        $lead->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lead deleted successfully',
        ]);
    }

    /**
     * Reassign lead to another user
     */
    public function reassign($companySlug, $moduleId, Request $request, $leadId): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();

        // Only owner/admin can reassign
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to reassign leads',
            ], 403);
        }

        $lead = Lead::where('module_id', $moduleId)->find($leadId);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'assigned_to' => ['required', 'exists:users,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $oldAssignee = $lead->assignedUser?->name ?? 'Unassigned';
        $lead->update(['assigned_to' => $request->assigned_to]);
        $newAssignee = $lead->fresh()->assignedUser->name;

        // Log activity
        \App\Models\Modules\Sales\ActivityLog::create([
            'lead_id' => $lead->id,
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name,
            'activity' => "Lead reassigned from {$oldAssignee} to {$newAssignee}",
            'details' => '',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lead reassigned successfully',
            'data' => $lead->fresh(['assignedUser']),
        ]);
    }

    /**
     * Dismiss (unassign) lead
     */
    public function dismiss($companySlug, $moduleId, $leadId): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();

        // Only owner/admin can dismiss
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to dismiss leads',
            ], 403);
        }

        $lead = Lead::where('module_id', $moduleId)->find($leadId);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found',
            ], 404);
        }

        $oldAssignee = $lead->assignedUser?->name ?? 'Unassigned';
        $lead->update(['assigned_to' => null]);

        // Log activity
        \App\Models\Modules\Sales\ActivityLog::create([
            'lead_id' => $lead->id,
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name,
            'activity' => "Lead dismissed from {$oldAssignee}",
            'details' => '',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lead dismissed successfully',
            'data' => $lead->fresh(),
        ]);
    }

    /**
     * Convert lead (mark as won/converted)
     */
    public function convert($companySlug, $moduleId, $leadId): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();

        $lead = Lead::where('module_id', $moduleId)->find($leadId);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found',
            ], 404);
        }

        // Check permission
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            if ($lead->assigned_to !== $user->id && $lead->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to convert this lead',
                ], 403);
            }
        }

        if ($lead->is_converted) {
            return response()->json([
                'success' => false,
                'message' => 'Lead is already converted',
            ], 400);
        }

        $lead->markAsConverted();

        // Log activity
        \App\Models\Modules\Sales\ActivityLog::create([
            'lead_id' => $lead->id,
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name,
            'activity' => "Lead marked as converted",
            'details' => '',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lead converted successfully',
            'data' => $lead->fresh(),
        ]);
    }

    /**
     * Batch operations
     */
    public function batchDelete($companySlug, $moduleId, Request $request): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();

        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete leads',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:leads,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        Lead::where('module_id', $moduleId)
            ->whereIn('id', $request->ids)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Leads deleted successfully',
        ]);
    }

    public function batchReassign($companySlug, $moduleId, Request $request): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();

        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to reassign leads',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['required', 'integer', 'exists:leads,id'],
            'assigned_to' => ['required', 'exists:users,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        Lead::where('module_id', $moduleId)
            ->whereIn('id', $request->lead_ids)
            ->update(['assigned_to' => $request->assigned_to]);

        return response()->json([
            'success' => true,
            'message' => 'Leads reassigned successfully',
        ]);
    }

    /**
     * Get lead statistics
     */
    public function stats($companySlug, $moduleId): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();

        $query = Lead::where('module_id', $moduleId);

        // If not owner/admin, only show stats for user's leads
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                    ->orWhere('created_by', $user->id);
            });
        }

        $totalLeads = $query->count();
        $convertedLeads = (clone $query)->where('is_converted', true)->count();
        $totalValue = (clone $query)->sum('value');
        $averageValue = $totalLeads > 0 ? $totalValue / $totalLeads : 0;

        // Leads by status
        $byStatus = (clone $query)
            ->select('status_id', DB::raw('count(*) as count'))
            ->with('status:id,name,name_ar,color')
            ->groupBy('status_id')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => $item->status,
                    'count' => $item->count,
                ];
            });

        // Leads by source
        $bySource = (clone $query)
            ->select('source_id', DB::raw('count(*) as count'))
            ->with('source:id,name,name_ar')
            ->groupBy('source_id')
            ->get()
            ->map(function ($item) {
                return [
                    'source' => $item->source,
                    'count' => $item->count,
                ];
            });

        // Leads by priority
        $byPriority = (clone $query)
            ->select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->get();

        // Recent leads (last 7 days)
        $recentLeads = (clone $query)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_leads' => $totalLeads,
                'converted_leads' => $convertedLeads,
                'conversion_rate' => $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 2) : 0,
                'total_value' => round($totalValue, 2),
                'average_value' => round($averageValue, 2),
                'recent_leads' => $recentLeads,
                'by_status' => $byStatus,
                'by_source' => $bySource,
                'by_priority' => $byPriority,
            ],
        ]);
    }
}
