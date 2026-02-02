<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use App\Http\Controllers\Controller;
use App\Models\Modules\Sales\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ServiceController extends Controller
{
    public function index($companySlug, $moduleId, $branchId, Request $request)
    {
        $services = Service::with(['category', 'branch'])
            ->forBranch($branchId)
            ->when(
                $request->category_id,
                fn($q) =>
                $q->where('category_id', $request->category_id)
            )
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Active services fetched successfully',
            'data' => $services,
        ]);
    }

    /**
     * Get all services (for dropdowns)
     */
    public function all($companySlug, $moduleId, $branchId)
    {
        $services = Service::forBranch($branchId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Active services fetched successfully',
            'data' => $services,
        ]);
    }


    public function show($companySlug, $moduleId, $branchId, $id)
    {
        $service = Service::forBranch($branchId)->with(['category', 'branch'])->findOrFail($id);
        return response()->json([
            'success' => true,
            'message' => 'Service fetched successfully',
            'data' => $service,
        ]);
    }

    public function store($companySlug, $moduleId, $branchId, Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',

            'price' => 'required|numeric|min:0',
            'cost' => 'required|numeric|min:0',

            'category_id' => 'nullable|exists:categories,id',

            'image' => 'nullable|image|max:2048',
            'pdf' => 'nullable|mimes:pdf|max:10240',
        ]);

        $imagePath = null;
        $pdfPath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')
                ->store('services/images', 'public');
        }

        if ($request->hasFile('pdf')) {
            $pdfPath = $request->file('pdf')
                ->store('services/pdfs', 'public');
        }

        $service = Service::create([
            'name' => $data['name'],
            'name_ar' => $data['name_ar'],
            'description' => $data['description'] ?? null,
            'description_ar' => $data['description_ar'] ?? null,
            'price' => $data['price'],
            'cost' => $data['cost'],
            'category_id' => $data['category_id'] ?? null,
            'branch_id' => $branchId,
            'image_path' => $imagePath,
            'pdf_path' => $pdfPath,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Service created successfully',
            'data' => $service->fresh(['category', 'branch']),
        ], 201);
    }

    /**
     * Update service (optionally replace image/pdf)
     */
    public function update($companySlug, $moduleId, $branchId, Request $request, $id)
    {
        $service = Service::forBranch($branchId)->findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'name_ar' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',

            'price' => 'sometimes|numeric|min:0',
            'cost' => 'sometimes|numeric|min:0',

            'category_id' => 'nullable|exists:categories,id',

            'image' => 'nullable|image|max:2048',
            'pdf' => 'nullable|mimes:pdf|max:10240',
        ]);

        if ($request->hasFile('image')) {
            if ($service->image_path) {
                Storage::disk('public')->delete($service->image_path);
            }

            $data['image_path'] = $request->file('image')
                ->store('services/images', 'public');
        }

        if ($request->hasFile('pdf')) {
            if ($service->pdf_path) {
                Storage::disk('public')->delete($service->pdf_path);
            }

            $data['pdf_path'] = $request->file('pdf')
                ->store('services/pdfs', 'public');
        }

        $service->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Service updated successfully',
            'data' => $service->fresh(['category', 'branch']),
        ]);
    }

    /**
     * Delete single service
     */
    public function destroy($companySlug, $moduleId, $branchId, $id)
    {
        $service = Service::forBranch($branchId)->findOrFail($id);

        if ($service->image_path) {
            Storage::disk('public')->delete($service->image_path);
        }

        if ($service->pdf_path) {
            Storage::disk('public')->delete($service->pdf_path);
        }

        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'Service deleted successfully',
            'data' => null,
        ]);
    }
    /**
     * Toggle active status
     */
    public function toggleStatus($companySlug, $moduleId, $branchId, $id)
    {
        $service = Service::forBranch($branchId)->findOrFail($id);

        $service->update([
            'is_active' => !$service->is_active
        ]);

        $status = $service->is_active === true ? "Active" : "Not Active";
        return response()->json([
            'success' => true,
            'message' => 'Service Is ' . $status,
            // 'data' => $service->fresh(['category', 'branch']),
        ]);
    }

    /**
     * Batch delete services
     */
    public function batchDelete($companySlug, $moduleId, $branchId, Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:services,id',
        ]);

        $services = Service::forBranch($branchId)->whereIn('id', $data['ids'])->get();

        foreach ($services as $service) {
            if ($service->image_path) {
                Storage::disk('public')->delete($service->image_path);
            }

            if ($service->pdf_path) {
                Storage::disk('public')->delete($service->pdf_path);
            }

            $service->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Services deleted successfully',
            'data' => null,
        ]);
    }
}
