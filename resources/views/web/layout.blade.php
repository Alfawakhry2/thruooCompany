<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Web Auth')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand" href="{{ url('/') }}">Thruoo</a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="{{ route('web.login') }}">Login</a>
            <a class="nav-link" href="{{ route('web.register') }}">Register</a>
        </div>
    </div>
</nav>

<main class="container py-5">
    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
