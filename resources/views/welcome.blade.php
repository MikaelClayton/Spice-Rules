<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="sunset">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
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
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-ghost">Log in</a>
                    <a href="{{ route('register') }}" class="btn btn-primary">Sign up</a>
                @endauth
            </div>
        </div>

        <main class="mx-auto flex min-h-[calc(100vh-4rem)] max-w-3xl flex-col items-center justify-center px-4 text-center">
            <img src="{{ asset('logo.png') }}" alt="{{ config('app.name') }}" class="mb-6 w-full max-w-md">
            <h1 class="sr-only">{{ config('app.name') }}</h1>
            <p class="mt-4 max-w-xl text-lg text-base-content/70">
                Create an account or log in to get started.
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary btn-lg">Go to dashboard</a>
                @else
                    <a href="{{ route('register') }}" class="btn btn-primary btn-lg">Sign up</a>
                    <a href="{{ route('login') }}" class="btn btn-outline btn-lg">Log in</a>
                @endauth
            </div>
        </main>
    </body>
</html>
