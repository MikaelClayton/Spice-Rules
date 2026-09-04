<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="spice">
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
        <div class="navbar bg-base-100 border-b border-base-300">
            <div class="navbar-start">
                <a href="{{ route('dashboard') }}" class="btn btn-ghost h-auto gap-2 px-2">
                    <img src="{{ asset('favicon.png') }}" alt="" class="h-8 w-8 rounded-lg">
                    <span class="text-xl font-semibold">{{ config('app.name') }}</span>
                </a>
            </div>
            <div class="navbar-end">
                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-ghost">
                        {{ Auth::user()->name }}
                    </div>
                    <ul tabindex="0" class="menu dropdown-content z-50 mt-3 w-52 rounded-box bg-base-100 p-2 shadow-lg">
                        <li>
                            <a href="{{ route('profile.edit') }}">Profile</a>
                        </li>
                        <li>
                            <button type="submit" form="logout-form" onmousedown="event.preventDefault()">Log out</button>
                        </li>
                    </ul>
                </div>
                <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
                    @csrf
                </form>
            </div>
        </div>

        <main class="mx-auto max-w-5xl px-4 py-6 sm:py-10">
            @yield('content')
        </main>
    </body>
</html>
