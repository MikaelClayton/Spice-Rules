<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="sunset">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', config('app.name'))</title>
        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-base-200">
        <div class="navbar bg-base-100/80 backdrop-blur border-b border-base-300">
            <div class="navbar-start">
                <a href="{{ route('home') }}" class="btn btn-ghost h-auto gap-2 px-2">
                    <img src="{{ asset('favicon.png') }}" alt="" class="h-8 w-8 rounded-lg">
                    <span class="text-xl font-semibold">{{ config('app.name') }}</span>
                </a>
            </div>
            <div class="navbar-end gap-2">
                <a href="{{ route('login') }}" class="btn btn-ghost">Log in</a>
                <a href="{{ route('register') }}" class="btn btn-primary">Sign up</a>
            </div>
        </div>

        <main class="flex min-h-[calc(100vh-4rem)] items-center justify-center px-4 py-12">
            @yield('content')
        </main>
    </body>
</html>
