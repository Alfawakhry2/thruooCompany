@extends('web.layout-auth')

@section('title', 'Tenant Login Success')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">{{ $message }}</h1>

                @if (!empty($company))
                    <p class="mb-1"><strong>Company:</strong> {{ $company->name }}</p>
                @endif

                <div class="alert alert-secondary mt-3">
                    <strong>Token:</strong> {{ $token }}
                </div>

                <div class="mt-3">
                    <a class="btn btn-primary" href="{{ $company?->url ?? url("/{$companySlug}") }}">Go to Company</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
