@extends('web.layout-auth')

@section('title', 'Register')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Register</h1>

                @if (!empty($message))
                    <div class="alert alert-info">{{ $message }}</div>
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

                <form method="POST" action="{{ url('/registration/register') }}">
                    @csrf

                    <div class="row">
                        <div class="col-md-6">
                            <h2 class="h6">Personal Info</h2>
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="personal[name]" class="form-control" value="{{ data_get($form, 'personal.name') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="personal[email]" class="form-control" value="{{ data_get($form, 'personal.email') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" name="personal[phone]" class="form-control" value="{{ data_get($form, 'personal.phone') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="personal[password]" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="personal[password_confirmation]" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h2 class="h6">Company Info</h2>
                            <div class="mb-3">
                                <label class="form-label">Company Name</label>
                                <input type="text" name="company[name]" class="form-control" value="{{ data_get($form, 'company.name') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Industry</label>
                                <select name="company[industry]" class="form-select" required>
                                    <option value="">Select industry</option>
                                    @foreach ($industries as $key => $label)
                                        <option value="{{ $key }}" @selected(data_get($form, 'company.industry') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Staff Count</label>
                                <select name="company[staff_count]" class="form-select" required>
                                    <option value="">Select staff count</option>
                                    @foreach ($staffCounts as $key => $label)
                                        <option value="{{ $key }}" @selected(data_get($form, 'company.staff_count') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" name="company[country]" class="form-control" value="{{ data_get($form, 'company.country') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">City</label>
                                <input type="text" name="company[city]" class="form-control" value="{{ data_get($form, 'company.city') }}" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Company Website</label>
                                <input type="url" name="company[website]" class="form-control" value="{{ data_get($form, 'company.website') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Business Email</label>
                                <input type="email" name="company[business_email]" class="form-control" value="{{ data_get($form, 'company.business_email') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" name="company[address]" class="form-control" value="{{ data_get($form, 'company.address') }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Subdomain (optional)</label>
                                <input type="text" name="subdomain" class="form-control" value="{{ data_get($form, 'subdomain') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Company Phone</label>
                                <input type="text" name="company[phone]" class="form-control" value="{{ data_get($form, 'company.phone') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Modules</label>
                                <div>
                                    @php
                                        $selectedModules = data_get($form, 'modules', ['sales']);
                                    @endphp
                                    @foreach ($modules as $key => $module)
                                        @if (!empty($module['available']))
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="modules[]" value="{{ $key }}" id="module-{{ $key }}" @checked(in_array($key, $selectedModules, true))>
                                                <label class="form-check-label" for="module-{{ $key }}">
                                                    {{ $module['name'] ?? $key }}
                                                </label>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Referral Code (optional)</label>
                        <input type="text" name="referral[code]" class="form-control" value="{{ data_get($form, 'referral.code') }}">
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Referral Relation (optional)</label>
                        <input type="text" name="referral[relation]" class="form-control" value="{{ data_get($form, 'referral.relation') }}">
                    </div>

                    <button type="submit" class="btn btn-primary">Create Account</button>
                </form>

                <div class="text-center mt-3">
                    <a href="{{ url('/auth/login') }}">Already have an account? Login</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
