@extends('web.layout-auth')

@section('title', 'Branch Check')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Tenant Branch Check</h1>

                <p class="mb-1"><strong>Company Slug:</strong> {{ $companySlug }}</p>
                <p class="mb-1"><strong>Module ID:</strong> {{ $moduleId }}</p>
                <p class="mb-1"><strong>Branch ID:</strong> {{ $branchId }}</p>

                @if ($company)
                    <p class="mb-1"><strong>Company Name:</strong> {{ $company->name }}</p>
                    <p class="mb-1"><strong>Domain:</strong> {{ $company->full_domain }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
