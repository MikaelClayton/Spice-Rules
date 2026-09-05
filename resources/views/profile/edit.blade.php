@extends('layouts.app')

@section('title', 'Profile — '.config('app.name'))

@php
    $tab = ($activeTab === 'geoguessr' || $errors->has('ncfa')) ? 'geoguessr' : 'account';
@endphp

@section('content')
    <div class="mb-5">
        <h1 class="text-3xl font-bold">Profile</h1>
        <p class="mt-1 text-base-content/70">Your Spice Rules account settings.</p>
    </div>

    @if (session('status'))
        <div role="alert" class="alert alert-success mb-6">
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="tabs tabs-box tabs-lg w-full" data-profile-tabs>
        <input
            type="radio"
            name="profile_tabs"
            class="tab grow"
            aria-label="Account"
            data-tab="account"
            @checked($tab === 'account')
        >
        <div class="tab-content mt-4">
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title">Your details</h2>
                    <p class="text-base-content/70">Update the name and email you use on Spice Rules.</p>

                    @if ($errors->any() && ! $errors->has('ncfa'))
                        <div role="alert" class="alert alert-error">
                            <span>{{ $errors->first() }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <fieldset class="fieldset">
                            <label class="label" for="name">Name</label>
                            <input
                                id="name"
                                type="text"
                                name="name"
                                value="{{ old('name', $user->name) }}"
                                class="input w-full @error('name') input-error @enderror"
                                required
                                autocomplete="name"
                            >
                        </fieldset>

                        <fieldset class="fieldset">
                            <label class="label" for="email">Email</label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email', $user->email) }}"
                                class="input w-full @error('email') input-error @enderror"
                                required
                                autocomplete="username"
                            >
                        </fieldset>

                        <fieldset class="fieldset">
                            <label class="label" for="color">Board colour</label>
                            <div class="flex items-center gap-3">
                                <input
                                    id="color"
                                    type="color"
                                    name="color"
                                    value="{{ old('color', $user->color ?? $user->boardColor()) }}"
                                    class="h-12 w-16 cursor-pointer rounded-lg border border-base-300 bg-base-100 p-1"
                                >
                                <p class="text-sm text-base-content/70">Used on graphs, the map, and today’s board.</p>
                            </div>
                        </fieldset>

                        <fieldset class="fieldset">
                            <label class="label" for="password">New password</label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                class="input w-full @error('password') input-error @enderror"
                                autocomplete="new-password"
                            >
                        </fieldset>

                        <fieldset class="fieldset">
                            <label class="label" for="password_confirmation">Confirm new password</label>
                            <input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                class="input w-full"
                                autocomplete="new-password"
                            >
                        </fieldset>

                        <button type="submit" class="btn btn-primary w-full">Save details</button>
                    </form>
                </div>
            </div>
        </div>

        <input
            type="radio"
            name="profile_tabs"
            class="tab grow"
            aria-label="GeoGuessr"
            data-tab="geoguessr"
            @checked($tab === 'geoguessr')
        >
        <div class="tab-content mt-4">
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="card-title">GeoGuessr</h2>
                        <div class="tooltip tooltip-right max-w-xs" data-tip="This is the _ncfa cookie from geoguessr.com. It lets Spice Rules pull your daily scores. Treat it like a password.">
                            <button type="button" class="btn btn-ghost btn-xs btn-circle" aria-label="What is _ncfa?">?</button>
                        </div>
                    </div>
                    <p class="text-base-content/70">
                        Paste your <code class="font-mono">_ncfa</code> cookie, then press Test. Active only turns on if GeoGuessr accepts it.
                    </p>

                    @if ($errors->has('ncfa'))
                        <div role="alert" class="alert alert-error">
                            <span>{{ $errors->first('ncfa') }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('profile.geoguessr.update') }}" class="space-y-4">
                        @csrf

                        <fieldset class="fieldset">
                            <label class="label" for="ncfa">_ncfa cookie</label>
                            <textarea
                                id="ncfa"
                                name="ncfa"
                                rows="3"
                                autocomplete="off"
                                spellcheck="false"
                                placeholder="Paste the cookie value only"
                                class="textarea w-full font-mono text-sm @error('ncfa') textarea-error @enderror"
                            >{{ old('ncfa', $geoguesser?->ncfa) }}</textarea>
                        </fieldset>

                        <div class="flex flex-wrap items-center gap-4">
                            <label class="label cursor-default gap-3">
                                <span>Active</span>
                                <input
                                    type="checkbox"
                                    class="toggle toggle-success"
                                    disabled
                                    {{ $geoguesser?->is_active ? 'checked' : '' }}
                                >
                            </label>
                            <button type="submit" class="btn btn-primary">Test</button>
                        </div>

                        @if (filled($geoguesser?->username) && $geoguesser?->is_active)
                            <p class="text-sm text-base-content/70">
                                Signed in as <span class="font-semibold">{{ $geoguesser->username }}</span>
                            </p>
                        @endif
                    </form>

                    <div class="collapse collapse-arrow bg-base-200 mt-4">
                        <input type="checkbox">
                        <div class="collapse-title font-medium">How to get your _ncfa</div>
                        <div class="collapse-content text-sm space-y-3">
                            <ol class="list-decimal pl-5 space-y-2">
                                <li>Log in at <a href="https://www.geoguessr.com" class="link link-primary" target="_blank" rel="noreferrer">geoguessr.com</a>.</li>
                                <li>Open DevTools: <kbd class="kbd kbd-sm">F12</kbd> or <kbd class="kbd kbd-sm">Cmd</kbd>+<kbd class="kbd kbd-sm">Option</kbd>+<kbd class="kbd kbd-sm">I</kbd> on a Mac.</li>
                                <li>Go to <strong>Application</strong> → <strong>Cookies</strong> → <code class="font-mono">https://www.geoguessr.com</code>.</li>
                                <li>Find the cookie named <code class="font-mono">_ncfa</code> (not nfca).</li>
                                <li>Copy the <strong>Value</strong> only, then paste it above.</li>
                            </ol>
                            <p>
                                <a
                                    href="https://www.youtube.com/watch?v=XSfTz9SZjTM"
                                    class="link link-primary"
                                    target="_blank"
                                    rel="noreferrer"
                                >Watch: Chrome DevTools Application tab, cookies, and local storage</a>
                            </p>
                            <p class="text-base-content/60">
                                Do not share this cookie. Anyone with it can use your GeoGuessr account.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
