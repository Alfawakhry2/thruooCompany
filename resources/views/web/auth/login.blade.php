@extends('web.layout')

@section('title', 'Login')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Login</h1>

                @if (session('status'))
                    <div class="alert alert-info">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('web.login.submit') }}" class="mb-4">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $email) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>

                @if (!empty($companies))
                    <h2 class="h6 mb-3">Select Company</h2>
                    <form method="POST" action="{{ route('web.login.company') }}">
                        @csrf
                        <input type="hidden" name="email" value="{{ $email }}">
                        <div class="mb-3">
                            <label class="form-label">Password (confirm)</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            @foreach ($companies as $company)
                                @php
                                    $companyValue = $company['subdomain'] ?? $company['id'] ?? '';
                                @endphp
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="company_id" id="company-{{ $loop->index }}" value="{{ $companyValue }}" required>
                                    <label class="form-check-label" for="company-{{ $loop->index }}">
                                        {{ $company['name'] ?? 'Company' }}
                                        @if (!empty($company['subdomain']))
                                            <span class="text-muted">({{ $company['subdomain'] }})</span>
                                        @endif
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <button type="submit" class="btn btn-outline-primary w-100">Continue</button>
                    </form>
                @endif

                <div class="text-center mt-3">
                    <a href="{{ route('web.register') }}">Create a new account</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
