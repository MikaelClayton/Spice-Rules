<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="spice">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', config('app.name'))</title>
        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <meta name="theme-color" content="#d82820">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
        @include('layouts.display-timezone')
        @if (app(\App\Services\Push\FirebaseConfig::class)->isClientConfigured())
            <script src="https://www.gstatic.com/firebasejs/{{ \App\Services\Push\FirebaseConfig::SDK_VERSION }}/firebase-app-compat.js"></script>
            <script src="https://www.gstatic.com/firebasejs/{{ \App\Services\Push\FirebaseConfig::SDK_VERSION }}/firebase-messaging-compat.js"></script>
        @endif
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-dvh flex-col bg-base-200 @yield('bodyClass')">
        <div class="navbar shrink-0 bg-base-100 border-b border-base-300">
            <div class="navbar-start">
                <a href="{{ route('dashboard') }}" class="btn btn-ghost h-auto gap-2 px-2">
                    <img src="{{ asset('favicon.png') }}" alt="" class="h-8 w-8 rounded-lg">
                    <span class="text-lg font-semibold sm:text-xl">{{ config('app.name') }}</span>
                </a>
            </div>
            <div class="navbar-end">
                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-ghost max-w-36 truncate sm:max-w-none">
                        {{ Auth::user()->name }}
                    </div>
                    <ul tabindex="0" class="menu dropdown-content z-50 mt-3 w-52 rounded-box bg-base-100 p-2 shadow-lg">
                        <li>
                            <a href="{{ route('profile.edit') }}">Profile</a>
                        </li>
                        @if (Auth::user()->isAdmin())
                            <li>
                                <a href="{{ route('admin.index') }}">Admin</a>
                            </li>
                        @endif
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

        <main @class([
            'mx-auto min-w-0 w-full max-w-5xl flex-1 px-3 sm:px-4',
            'flex min-h-0 flex-col overflow-hidden py-2 pb-[max(0.5rem,env(safe-area-inset-bottom))]' => View::hasSection('playLayout'),
            'py-4 sm:py-10' => ! View::hasSection('playLayout'),
        ])>
            @yield('content')
        </main>
        @unless (View::hasSection('hideSupportWidget'))
            <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
            <script type="text/javascript">
                function mountWidget(p) {
                    window.dxSupportWidget = window.dxSupportWidget || {};
                    Object.entries(p).forEach(([k, v]) => {
                        window.dxSupportWidget[k] = v;
                    })

                    const s = document.createElement("script");
                    s.type = "text/javascript";
                    s.async = true;
                    s.src = "https://web.divblox.app/widgets/dxSupportWidget.js";

                    const s0 = document.getElementsByTagName("script")[0];
                    s0.parentNode.insertBefore(s, s0);
                };

                mountWidget({projectGuid: "5d01f72d3089fff551c15982e277eeef"});
            </script>
        @endunless
    </body>
</html>
