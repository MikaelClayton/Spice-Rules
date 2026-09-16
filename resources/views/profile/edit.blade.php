@extends('layouts.app')

@section('title', 'Profile — '.config('app.name'))

@php
    $tab = match (true) {
        $errors->has('ncfa') || $errors->has('sync') => 'geoguessr',
        $errors->has('allow_pub_golf_location') => 'pub-golf',
        $errors->has('fit_ish_user_id')
            || $errors->has('fit_ish_serial')
            || $errors->has('studio_ids')
            || $errors->has('studio_id')
            || $errors->has('code')
            || $errors->has('studio')
            || $errors->has('fit_ish')
            || $errors->has('timezone')
            || $errors->has('name') && request()->routeIs('profile.fit-ish.*') => 'fit-ish',
        default => $activeTab,
    };
    $accountError = $errors->any()
        && ! $errors->has('ncfa')
        && ! $errors->has('sync')
        && ! $errors->has('allow_pub_golf_location')
        && ! $errors->has('fit_ish_user_id')
        && ! $errors->has('fit_ish_serial')
        && ! $errors->has('studio_ids')
        && ! $errors->has('studio_id')
        && ! $errors->has('studio')
        && ! $errors->has('fit_ish')
        && ! $errors->has('code')
        && ! $errors->has('timezone');
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

    <div class="tabs tabs-box tabs-lg w-full" data-tabs data-default-tab="account">
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

                    @if ($accountError)
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

            @if ($firebase->isClientConfigured())
                <div
                    class="card bg-base-100 shadow-xl mt-4"
                    data-push-notifications
                    data-firebase-config='@json($firebase->webConfig())'
                    data-vapid-key="{{ config('services.firebase.vapid_key') }}"
                    data-token-url="{{ route('profile.device-tokens.store') }}"
                >
                    <div class="card-body">
                        <h2 class="card-title">Notifications</h2>
                        <button type="button" class="btn btn-primary w-full sm:w-auto" data-enable-push>
                            Enable notifications
                        </button>
                    </div>
                </div>
            @endif
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

                    @if ($errors->has('ncfa') || $errors->has('sync'))
                        <div role="alert" class="alert alert-error">
                            <span>{{ $errors->first('ncfa') ?: $errors->first('sync') }}</span>
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

                    @if ($geoguesser?->is_active && filled($geoguesser?->ncfa))
                        <form method="POST" action="{{ route('profile.geoguessr.sync') }}" class="mt-4 space-y-2">
                            @csrf
                            <button type="submit" class="btn btn-secondary">Sync scores</button>
                            <p class="text-sm text-base-content/70">
                                Pull your latest daily onto the board without waiting for the 30-minute refresh.
                            </p>
                        </form>
                    @endif

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

            @if ($canBrowseChallenges)
                <section
                    class="card bg-base-100 shadow-xl mt-4"
                    data-profile-challenges
                    data-challenges-url="{{ route('profile.geoguessr.challenges') }}"
                    data-share-url="{{ route('profile.geoguessr.challenges.share') }}"
                    data-challenge-has-more="{{ $challengeHasMore ? 'true' : 'false' }}"
                >
                    <div class="card-body gap-4 p-4 sm:p-6">
                        <div>
                            <h2 class="card-title">Challenges by player</h2>
                            <p class="mt-1 text-sm text-base-content/70">Browse every daily on the board. Filter by player.</p>
                        </div>

                        @if ($challengeGrid === [])
                            <p class="text-sm text-base-content/60">No challenges have been synced yet.</p>
                        @else
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Player</p>
                                <div class="mt-2 flex gap-2 overflow-x-auto pb-1" data-filter-group="player">
                                    <button type="button" class="btn btn-primary btn-sm shrink-0" data-filter="all" aria-pressed="true">
                                        Everyone
                                    </button>
                                    @foreach ($challengePlayers as $player)
                                        <button type="button" class="btn btn-ghost btn-sm shrink-0" data-filter="{{ $player['id'] }}" aria-pressed="false">
                                            <span class="inline-block h-2.5 w-2.5 rounded-full" style="background: {{ $player['color'] }}"></span>
                                            {{ $player['label'] }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <p class="hidden text-sm" data-share-status></p>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2" data-challenge-grid>
                                @foreach ($challengeGrid as $challenge)
                                    <button
                                        type="button"
                                        class="card bg-base-200 shadow-sm w-full cursor-pointer text-left"
                                        data-challenge-card
                                        data-challenge-id="{{ $challenge['id'] }}"
                                        data-challenge-date="{{ $challenge['dateKey'] }}"
                                        data-player-id="{{ $challenge['playerId'] }}"
                                    >
                                        <div class="card-body gap-3 p-3.5 sm:p-4">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">{{ $challenge['date'] }}</p>
                                                    <p class="mt-1 flex min-w-0 items-center gap-2 font-semibold leading-tight">
                                                        <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $challenge['color'] }}"></span>
                                                        <span class="truncate">{{ $challenge['player'] }}</span>
                                                    </p>
                                                </div>
                                                <div class="flex shrink-0 flex-col items-end gap-1">
                                                    <span class="badge badge-ghost">{{ $challenge['map'] }}</span>
                                                    <span @class(['badge badge-secondary', 'hidden' => ! $challenge['isDoneAsTeam']]) data-field="team">Team</span>
                                                </div>
                                            </div>
                                            <p class="text-2xl font-bold leading-none tabular-nums">
                                                @if ($challenge['score'] !== null)
                                                    {{ number_format($challenge['score']) }}
                                                @else
                                                    <span class="text-base font-medium text-base-content/60">Score pending</span>
                                                @endif
                                            </p>
                                            <p class="text-xs tabular-nums text-base-content/60">
                                                @if ($challenge['distance'] !== null)
                                                    {{ number_format($challenge['distance'] / 1000, 1) }} km
                                                @else
                                                    Distance pending
                                                @endif
                                                <span class="text-base-content/30">·</span>
                                                @if ($challenge['steps'] !== null)
                                                    {{ number_format($challenge['steps']) }} steps
                                                @else
                                                    Steps pending
                                                @endif
                                            </p>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                            <p class="hidden text-sm text-base-content/60" data-challenge-empty>No challenges for this player yet.</p>
                            <button
                                type="button"
                                class="btn btn-outline w-full sm:w-auto {{ $challengeHasMore ? '' : 'hidden' }}"
                                data-challenge-more
                            >Load more</button>
                            <template data-challenge-card-template>
                                <button
                                    type="button"
                                    class="card bg-base-200 shadow-sm w-full cursor-pointer text-left"
                                    data-challenge-card
                                >
                                    <div class="card-body gap-3 p-3.5 sm:p-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50" data-field="date"></p>
                                                <p class="mt-1 flex min-w-0 items-center gap-2 font-semibold leading-tight">
                                                    <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full" data-field="color"></span>
                                                    <span class="truncate" data-field="player"></span>
                                                </p>
                                            </div>
                                            <div class="flex shrink-0 flex-col items-end gap-1">
                                                <span class="badge badge-ghost" data-field="map"></span>
                                                <span class="badge badge-secondary hidden" data-field="team">Team</span>
                                            </div>
                                        </div>
                                        <p class="text-2xl font-bold leading-none tabular-nums" data-field="score"></p>
                                        <p class="text-xs tabular-nums text-base-content/60" data-field="meta"></p>
                                    </div>
                                </button>
                            </template>

                            <dialog id="share-challenge-as-team" class="modal" data-share-modal>
                                <div class="modal-box p-4 sm:p-6">
                                    <h2 class="text-lg font-bold">Sync as team</h2>
                                    <p class="mt-1 text-sm text-base-content/70" data-share-copy>
                                        Copy this challenge and its rounds onto the people you select.
                                    </p>
                                    <div role="alert" class="alert alert-error mt-4 hidden" data-share-error>
                                        <span></span>
                                    </div>
                                    <form method="POST" action="{{ route('profile.geoguessr.challenges.share') }}" class="mt-4 space-y-4" data-share-form>
                                        @csrf
                                        <input type="hidden" name="challenge_id" value="" data-share-challenge-id>
                                        <fieldset class="fieldset">
                                            <legend class="label">Players</legend>
                                            <div class="max-h-72 space-y-1 overflow-y-auto">
                                                @forelse ($shareTargets as $player)
                                                    <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg px-2 py-2 hover:bg-base-200" data-share-row>
                                                        <input
                                                            type="checkbox"
                                                            name="geoguesser_ids[]"
                                                            value="{{ $player['id'] }}"
                                                            class="checkbox checkbox-primary"
                                                            data-share-target
                                                            data-dates="{{ implode(',', $player['dates']) }}"
                                                        >
                                                        <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $player['color'] }}"></span>
                                                        <span class="min-w-0 flex-1 truncate font-medium">{{ $player['label'] }}</span>
                                                        <span class="hidden shrink-0 text-xs text-base-content/55" data-share-unavailable></span>
                                                    </label>
                                                @empty
                                                    <p class="text-sm text-base-content/60">No active GeoGuessr profiles to share with.</p>
                                                @endforelse
                                            </div>
                                        </fieldset>
                                        <div class="modal-action mt-4 flex-col gap-2 sm:flex-row">
                                            <button type="button" class="btn w-full sm:w-auto" data-share-cancel>Cancel</button>
                                            <button type="submit" class="btn btn-primary w-full sm:w-auto" data-share-submit>Sync as team</button>
                                        </div>
                                    </form>
                                </div>
                                <form method="dialog" class="modal-backdrop">
                                    <button>close</button>
                                </form>
                            </dialog>
                        @endif
                    </div>
                </section>
            @endif
        </div>

        <input
            type="radio"
            name="profile_tabs"
            class="tab grow"
            aria-label="Fit-Ish"
            data-tab="fit-ish"
            @checked($tab === 'fit-ish')
        >
        <div class="tab-content mt-4 space-y-4">
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title">Fit-Ish</h2>
                    <p class="text-base-content/70">
                        Link your Lionheart user ID so Spice Rules can pull classes. The clubhouse tile appears after this is saved.
                    </p>

                    @if ($errors->has('fit_ish_user_id') || $errors->has('fit_ish_serial') || $errors->has('studio_ids') || $errors->has('fit_ish'))
                        <div role="alert" class="alert alert-error">
                            <span>{{ $errors->first('fit_ish_user_id') ?: $errors->first('fit_ish_serial') ?: $errors->first('studio_ids') ?: $errors->first('fit_ish') }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('profile.fit-ish.update') }}" class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <fieldset class="fieldset">
                            <label class="label" for="fit_ish_user_id">Lionheart user ID</label>
                            <input
                                id="fit_ish_user_id"
                                type="text"
                                inputmode="numeric"
                                name="fit_ish_user_id"
                                value="{{ old('fit_ish_user_id', $user->fit_ish_user_id) }}"
                                class="input w-full font-mono @error('fit_ish_user_id') input-error @enderror"
                                autocomplete="off"
                                placeholder="13138221"
                            >
                        </fieldset>

                        <fieldset class="fieldset">
                            <label class="label" for="fit_ish_serial">Lionheart serial</label>
                            <input
                                id="fit_ish_serial"
                                type="text"
                                name="fit_ish_serial"
                                value="{{ old('fit_ish_serial', $user->fit_ish_serial) }}"
                                class="input w-full font-mono @error('fit_ish_serial') input-error @enderror"
                                autocomplete="off"
                                placeholder="On the back of the puck"
                            >
                            <p class="label text-base-content/60">Used to look up today's class if Lionheart does not return a session list.</p>
                        </fieldset>

                        <fieldset class="fieldset">
                            <legend class="label">Studios</legend>
                            <div class="max-h-64 space-y-1 overflow-y-auto rounded-lg border border-base-300 p-2">
                                @forelse ($fitIshStudios as $studio)
                                    <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg px-2 py-2 hover:bg-base-200">
                                        <input
                                            type="checkbox"
                                            name="studio_ids[]"
                                            value="{{ $studio->id }}"
                                            class="checkbox checkbox-primary"
                                            @checked(in_array($studio->id, old('studio_ids', $linkedStudioIds), false))
                                        >
                                        <span class="min-w-0 flex-1">
                                            <span class="block font-medium">{{ $studio->name }}</span>
                                            <span class="block text-xs text-base-content/60">{{ $studio->code }} · {{ $studio->timezone }}</span>
                                        </span>
                                    </label>
                                @empty
                                    <p class="px-2 py-3 text-sm text-base-content/60">Add a studio below, then tick it here.</p>
                                @endforelse
                            </div>
                        </fieldset>

                        <button type="submit" class="btn btn-primary w-full sm:w-auto">Save Fit-Ish</button>
                    </form>

                    @if (filled($user->fit_ish_user_id))
                        <form method="POST" action="{{ route('profile.fit-ish.sync') }}" class="mt-4 space-y-2">
                            @csrf
                            <button type="submit" class="btn btn-secondary">Sync classes</button>
                            <p class="text-sm text-base-content/70">Pull your latest Lionheart sessions without waiting for the 30-minute refresh.</p>
                        </form>
                    @endif

                    <div class="collapse collapse-arrow bg-base-200 mt-4">
                        <input type="checkbox">
                        <div class="collapse-title font-medium">How to get your user ID</div>
                        <div class="collapse-content text-sm space-y-2">
                            <p>It is not shown in the F45 app. Open a completed Lionheart class with Charles (or Proxyman) and copy <code class="font-mono">user_id</code> from the request URL. Serial is on the back of the heart-rate puck.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-base-100 shadow-xl">
                <div class="card-body gap-4">
                    <h2 class="card-title">Studios</h2>
                    <p class="text-base-content/70">Add the studios you visit. The code and studio ID come from the Lionheart session JSON.</p>

                    @if ($errors->has('studio_id') || $errors->has('code') || $errors->has('timezone') || $errors->has('studio') || ($errors->has('name') && $tab === 'fit-ish'))
                        <div role="alert" class="alert alert-error">
                            <span>{{ $errors->first('studio') ?: $errors->first('studio_id') ?: $errors->first('code') ?: $errors->first('timezone') ?: $errors->first('name') }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('profile.fit-ish.studios.store') }}" class="grid gap-3 sm:grid-cols-2">
                        @csrf
                        <fieldset class="fieldset">
                            <label class="label" for="studio_id">Studio ID</label>
                            <input id="studio_id" type="number" name="studio_id" value="{{ old('studio_id') }}" class="input w-full @error('studio_id') input-error @enderror" required placeholder="5061">
                        </fieldset>
                        <fieldset class="fieldset">
                            <label class="label" for="studio_code">Code</label>
                            <input id="studio_code" type="text" name="code" value="{{ old('code') }}" class="input w-full font-mono @error('code') input-error @enderror" required placeholder="ojb7">
                        </fieldset>
                        <fieldset class="fieldset sm:col-span-2">
                            <label class="label" for="studio_name">Name</label>
                            <input id="studio_name" type="text" name="name" value="{{ old('name') }}" class="input w-full @error('name') input-error @enderror" required placeholder="F45 Faerie Glen">
                        </fieldset>
                        <fieldset class="fieldset">
                            <label class="label" for="studio_timezone">Timezone</label>
                            <input id="studio_timezone" type="text" name="timezone" value="{{ old('timezone', 'Africa/Johannesburg') }}" class="input w-full @error('timezone') input-error @enderror" required>
                        </fieldset>
                        <fieldset class="fieldset justify-end">
                            <label class="label cursor-pointer justify-start gap-3 pt-8">
                                <input type="hidden" name="is_loaner" value="0">
                                <input type="checkbox" name="is_loaner" value="1" class="checkbox checkbox-primary" @checked(old('is_loaner'))>
                                <span>Loaner studio</span>
                            </label>
                        </fieldset>
                        <div class="sm:col-span-2">
                            <button type="submit" class="btn btn-outline">Add studio</button>
                        </div>
                    </form>

                    <ul class="space-y-3">
                        @foreach ($fitIshStudios as $studio)
                            <li class="rounded-xl border border-base-300 p-3">
                                <form method="POST" action="{{ route('profile.fit-ish.studios.update', $studio) }}" class="grid gap-3 sm:grid-cols-2">
                                    @csrf
                                    @method('PATCH')
                                    <fieldset class="fieldset">
                                        <label class="label">Studio ID</label>
                                        <input type="number" name="studio_id" value="{{ old('studio_id', $studio->external_id) }}" class="input w-full" required>
                                    </fieldset>
                                    <fieldset class="fieldset">
                                        <label class="label">Code</label>
                                        <input type="text" name="code" value="{{ old('code', $studio->code) }}" class="input w-full font-mono" required>
                                    </fieldset>
                                    <fieldset class="fieldset sm:col-span-2">
                                        <label class="label">Name</label>
                                        <input type="text" name="name" value="{{ old('name', $studio->name) }}" class="input w-full" required>
                                    </fieldset>
                                    <fieldset class="fieldset">
                                        <label class="label">Timezone</label>
                                        <input type="text" name="timezone" value="{{ old('timezone', $studio->timezone) }}" class="input w-full" required>
                                    </fieldset>
                                    <fieldset class="fieldset">
                                        <label class="label cursor-pointer justify-start gap-3 pt-8">
                                            <input type="hidden" name="is_loaner" value="0">
                                            <input type="checkbox" name="is_loaner" value="1" class="checkbox checkbox-primary" @checked(old('is_loaner', $studio->is_loaner))>
                                            <span>Loaner studio</span>
                                        </label>
                                    </fieldset>
                                    <div class="flex flex-wrap gap-2 sm:col-span-2">
                                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('profile.fit-ish.studios.destroy', $studio) }}" class="mt-2">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-ghost text-error">Delete</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <input
            type="radio"
            name="profile_tabs"
            class="tab grow"
            aria-label="Pub Golf"
            data-tab="pub-golf"
            @checked($tab === 'pub-golf')
        >
        <div class="tab-content mt-4">
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title">Pub Golf</h2>
                    <p class="text-base-content/70">How Spice Rules uses your phone while you log drinks.</p>

                    @if ($errors->has('allow_pub_golf_location'))
                        <div role="alert" class="alert alert-error">
                            <span>{{ $errors->first('allow_pub_golf_location') }}</span>
                        </div>
                    @endif

                    <form
                        method="POST"
                        action="{{ route('profile.pub-golf.update') }}"
                        class="space-y-4"
                        data-pub-golf-location
                        data-save-url="{{ route('profile.pub-golf.update') }}"
                    >
                        @csrf
                        @method('PATCH')

                        <div class="flex w-full min-w-0 items-start gap-3">
                            <input type="hidden" name="allow_pub_golf_location" value="0">
                            <input
                                id="allow_pub_golf_location"
                                type="checkbox"
                                name="allow_pub_golf_location"
                                value="1"
                                class="toggle toggle-primary mt-0.5 shrink-0"
                                @checked(old('allow_pub_golf_location', $user->allow_pub_golf_location))
                            >
                            <label for="allow_pub_golf_location" class="min-w-0 flex-1 cursor-pointer">
                                <span class="font-medium">Allow location</span>
                                <span class="mt-0.5 block text-sm font-normal whitespace-normal text-base-content/70">
                                    Turning this on asks your phone now. After that, a pin is only taken when you log a drink. If you refuse, this stays off so we do not ask again.
                                </span>
                            </label>
                        </div>

                        <p class="hidden text-sm" data-pub-golf-location-status></p>

                        <button type="submit" class="btn btn-primary" data-pub-golf-location-save>Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
