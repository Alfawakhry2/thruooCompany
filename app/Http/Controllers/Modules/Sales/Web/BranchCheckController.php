<?php

namespace App\Http\Controllers\Modules\Sales\Web;

use App\Http\Controllers\Controller;
use App\Models\Landlord\Company;

class BranchCheckController extends Controller
{
    public function show(string $companySlug, string $moduleId, string $branchId)
    {
        $company = Company::current() ?? Company::on('mysql')->where('slug', $companySlug)->first();

        return view('web.tenant.branch-check', [
            'company' => $company,
            'companySlug' => $companySlug,
            'moduleId' => $moduleId,
            'branchId' => $branchId,
        ]);
    }
}
