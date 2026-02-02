@extends('web.layout-auth')

@section('title', 'Login Success')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">{{ $message }}</h1>

                @if (!empty($data['user']))
                    <p class="mb-1"><strong>User:</strong> {{ $data['user']['name'] ?? '' }} ({{ $data['user']['email'] ?? '' }})</p>
                @endif

                @if (!empty($data['company']))
                    <p class="mb-1"><strong>Company:</strong> {{ $data['company']['name'] ?? '' }}</p>
                    @if (!empty($data['company']['domain']))
                        <p class="mb-1"><strong>Domain:</strong> {{ $data['company']['domain'] }}</p>
                    @endif
                @endif

                @if (!empty($data['token']))
                    <div class="alert alert-secondary mt-3">
                        <strong>Token:</strong> {{ $data['token'] }}
                    </div>
                @endif

                @if (!empty($data['redirect']))
                    <div class="mt-3">
                        <a class="btn btn-primary" href="{{ $data['redirect']['url'] ?? '#' }}">Go to Company</a>
                        @if (!empty($data['redirect']['legacy_url']))
                            <a class="btn btn-outline-secondary" href="{{ $data['redirect']['legacy_url'] }}">Legacy URL</a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
