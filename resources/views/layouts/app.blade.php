<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'POS') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-screen text-slate-800 antialiased" x-data="{ nav: false }">
    @auth
        @include('partials.sidebar')
        <div class="lg:pl-64">
            @yield('content')
        </div>
    @else
        @yield('content')
    @endauth
    @stack('scripts')
</body>
</html>
