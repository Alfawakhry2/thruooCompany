<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use App\Http\Controllers\Controller;
use App\Models\Modules\Sales\Lead;
use App\Models\Modules\Sales\Contract;
use App\Models\Modules\Sales\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ContractController extends Controller
{
    /**
     * Get all contracts for a lead
     */
    public function index($companySlug, $moduleId, $branchId, $leadId): JsonResponse
    {
        $lead = Lead::forBranch($branchId)->where('module_id', $moduleId)->find($leadId);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found',
            ], 404);
        }

        $contracts = $lead->contracts()->with('uploader')->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $contracts,
        ]);
    }

    /**
     * Upload a new contract
     */
    public function store(Request $request, $companySlug, $moduleId, $branchId, $leadId): JsonResponse
    {
        $lead = Lead::forBranch($branchId)->where('module_id', $moduleId)->find($leadId);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'document' => ['required', 'file', 'max:10240'], // Max 10MB
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Store the file
        $path = $request->file('document')->store('contracts', 'public');

        // Create contract
        $contract = Contract::create([
            'lead_id' => $lead->id,
            'module_id' => $moduleId,
            'branch_id' => $branchId,
            'document_name' => $request->file('document')->getClientOriginalName(),
            'document_path' => $path,
            'notes' => $request->notes,
            'uploaded_by' => Auth::id(),
        ]);

        // Log activity
        ActivityLog::create([
            'lead_id' => $lead->id,
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name,
            'activity' => "Contract uploaded to lead: {$lead->name}",
            'details' => "Document: {$contract->document_name}",
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contract uploaded successfully',
            'data' => $contract->load('uploader'),
        ], 201);
    }

    /**
     * Get a specific contract
     */
    public function show($companySlug, $moduleId, $branchId, $leadId, $contractId): JsonResponse
    {
        $lead = Lead::forBranch($branchId)->where('module_id', $moduleId)->find($leadId);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found',
            ], 404);
        }

        $contract = $lead->contracts()->with('uploader')->find($contractId);

        if (!$contract) {
            return response()->json([
                'success' => false,
                'message' => 'Contract not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $contract,
        ]);
    }

    /**
     * Update a contract
     */
    public function update(Request $request, $companySlug, $moduleId, $branchId, $leadId, $contractId): JsonResponse
    {
        $lead = Lead::forBranch($branchId)->where('module_id', $moduleId)->find($leadId);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found',
            ], 404);
        }

        $contract = $lead->contracts()->find($contractId);

        if (!$contract) {
            return response()->json([
                'success' => false,
                'message' => 'Contract not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'document' => ['nullable', 'file', 'max:10240'], // Max 10MB
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // If new document uploaded, replace old one
        if ($request->hasFile('document')) {
            // Delete old file
            if ($contract->document_path) {
                Storage::disk('public')->delete($contract->document_path);
            }

            // Store new file
            $path = $request->file('document')->store('contracts', 'public');

            $contract->update([
                'document_name' => $request->file('document')->getClientOriginalName(),
                'document_path' => $path,
            ]);
        }

        // Update notes if provided
        if ($request->has('notes')) {
            $contract->update(['notes' => $request->notes]);
        }

        // Log activity
        ActivityLog::create([
            'lead_id' => $lead->id,
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name,
            'activity' => "Contract updated for lead: {$lead->name}",
            'details' => "Document: {$contract->document_name}",
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contract updated successfully',
            'data' => $contract->fresh(['uploader']),
        ]);
    }

    /**
     * Delete a contract
     */
    public function destroy($companySlug, $moduleId, $branchId, $leadId, $contractId): JsonResponse
    {
        $lead = Lead::forBranch($branchId)->where('module_id', $moduleId)->find($leadId);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found',
            ], 404);
        }

        $contract = $lead->contracts()->find($contractId);

        if (!$contract) {
            return response()->json([
                'success' => false,
                'message' => 'Contract not found',
            ], 404);
        }

        // Delete file from storage
        if ($contract->document_path) {
            Storage::disk('public')->delete($contract->document_path);
        }

        // Log activity before deleting
        ActivityLog::create([
            'lead_id' => $lead->id,
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name,
            'activity' => "Contract deleted from lead: {$lead->name}",
            'details' => "Document: {$contract->document_name}",
        ]);

        $contract->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contract deleted successfully',
        ]);
    }
}
