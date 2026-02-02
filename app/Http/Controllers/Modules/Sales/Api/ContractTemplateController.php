<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use App\Http\Controllers\Controller;
use App\Models\Modules\Module;
use App\Models\Modules\Sales\ContractTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ContractTemplateController extends Controller
{
    public function index($companySlug, $moduleId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $templates = ContractTemplate::where('module_id', $moduleId)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    public function store($companySlug, $moduleId, Request $request): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $template = ContractTemplate::create([
            'module_id' => $moduleId,
            'title' => $request->title,
            'content' => $request->content,
            'notes' => $request->notes,
            'is_active' => $request->is_active ?? true,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contract template created successfully',
            'data' => $template,
        ], 201);
    }

    public function show($companySlug, $moduleId, $templateId): JsonResponse
    {
        $template = ContractTemplate::where('module_id', $moduleId)->find($templateId);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Contract template not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }

    public function update($companySlug, $moduleId, Request $request, $templateId): JsonResponse
    {
        $template = ContractTemplate::where('module_id', $moduleId)->find($templateId);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Contract template not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'content' => ['sometimes', 'required', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $template->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Contract template updated successfully',
            'data' => $template,
        ]);
    }

    public function destroy($companySlug, $moduleId, $templateId): JsonResponse
    {
        $template = ContractTemplate::where('module_id', $moduleId)->find($templateId);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Contract template not found',
            ], 404);
        }

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contract template deleted successfully',
        ]);
    }
}
