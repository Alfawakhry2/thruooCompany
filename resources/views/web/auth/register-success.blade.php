@extends('web.layout-auth')

@section('title', 'Registration Success')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">{{ $message }}</h1>

                @if (!empty($data['tenant']))
                    <p class="mb-1"><strong>Owner:</strong> {{ $data['tenant']['name'] ?? '' }} ({{ $data['tenant']['email'] ?? '' }})</p>
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

                @if (!empty($data['redirect']['url']))
                    <div class="mt-3">
                        <a class="btn btn-primary" href="{{ $data['redirect']['url'] }}">Go to Company</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
