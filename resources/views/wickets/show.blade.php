@extends('layouts.app')

@section('title', $group->name.' — Wickets — '.config('app.name'))

@php
    $tab = $activeTab;
    $fineInputFailed = $errors->hasAny(['reason', 'type', 'punishment', 'issued_to_user_id', 'issued_to_user_ids'])
        || ($errors->has('sips') && (old('type') || old('punishment')));
    $oldPunishment = old('punishment');
    $selectedSipCount = 0;
    if (is_string($oldPunishment) && preg_match('/^sips_([1-7])$/', $oldPunishment, $sipMatch) === 1) {
        $selectedSipCount = (int) $sipMatch[1];
    } elseif (old('type') === 'sips') {
        $selectedSipCount = max(0, min($maxSipFine, (int) old('sips', 0)));
    }
    if ($fineInputFailed) {
        $tab = 'fine';
    } elseif ($errors->has('sips')) {
        $tab = 'drink';
    } elseif ($errors->hasAny(['user_ids', 'user', 'role', 'is_tournament', 'name'])) {
        $tab = 'people';
    }
@endphp

@section('content')
    <div class="mb-4">
        <a href="{{ route('wickets.index') }}" class="btn btn-ghost btn-sm -ml-2">← Back</a>
    </div>

    <div class="mb-5">
        <h1 class="text-2xl font-bold sm:text-3xl">{{ $group->name }}</h1>
        <p class="mt-1 text-base-content/70">
            {{ $group->users->count() }} {{ $group->users->count() === 1 ? 'player' : 'players' }}
            @if ($group->isTournament())
                · Tournament
            @endif
        </p>
    </div>

    @if (session('status'))
        <div role="alert" class="alert alert-success mb-5">
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div
        data-live-poll
        data-poll-url="{{ route('wickets.live', $group) }}"
        data-revision="{{ $revision }}"
    >
        <div data-live-region="owe">
            @include('wickets.owe')
        </div>

        <div class="tabs tabs-box w-full">
        <input
            type="radio"
            name="wickets_tabs"
            class="tab grow"
            aria-label="Board"
            @checked($tab === 'board')
        >
        <div class="tab-content mt-4 space-y-3">
            <div data-live-region="board">
                @include('wickets.board')
            </div>
        </div>

        <input
            type="radio"
            name="wickets_tabs"
            class="tab grow"
            aria-label="Fine"
            @checked($tab === 'fine')
        >
        <div class="tab-content mt-4 overflow-visible">
            <div class="card overflow-visible bg-base-100 shadow-xl">
                <div class="card-body gap-5 overflow-visible p-4 sm:p-5">
                    <div>
                        <h2 class="card-title">Give a fine</h2>
                        <p class="text-sm text-base-content/70">Pick people, pick the punishment, say why.</p>
                    </div>

                    @if ($errors->hasAny(['reason', 'type', 'sips', 'punishment', 'issued_to_user_id', 'issued_to_user_ids']))
                        <div role="alert" class="alert alert-error">
                            <span>{{ $errors->first() }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('wickets.fines.store', $group) }}" class="space-y-5">
                        @csrf

                        <fieldset class="fieldset">
                            <legend class="label">Who</legend>
                            <p class="mb-2 text-sm text-base-content/70">Search and pick one or more players.</p>
                            @include('wickets.people-picker', [
                                'pickerId' => 'fine',
                                'people' => $group->users,
                                'inputName' => 'issued_to_user_ids[]',
                                'selectedIds' => collect(old('issued_to_user_ids', old('issued_to_user_id') ? [old('issued_to_user_id')] : [])),
                            ])
                        </fieldset>

                        <fieldset class="fieldset">
                            <legend class="label">Sips</legend>
                            <p class="mb-2 text-sm text-base-content/70">8 sips become a down down.</p>
                            <div
                                class="rounded-2xl bg-base-200 px-3 py-4 sm:px-5"
                                data-sip-stepper
                                data-max="{{ $maxSipFine }}"
                                @if ($selectedSipCount > 0) data-has-sips @endif
                                style="--sip-fill: {{ $selectedSipCount / $maxSipFine }}"
                            >
                                <div class="flex items-center justify-center gap-4 sm:gap-6">
                                    <button
                                        type="button"
                                        class="btn btn-circle btn-lg"
                                        data-sip-dec
                                        aria-label="Fewer sips"
                                        @disabled($selectedSipCount <= 0)
                                    >
                                        −
                                    </button>

                                    <div class="flex w-24 flex-col items-center gap-2">
                                        <svg
                                            class="h-36 w-20 text-base-content"
                                            viewBox="0 0 80 120"
                                            aria-hidden="true"
                                        >
                                            <defs>
                                                <linearGradient id="wicket-beer-grad" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0%" stop-color="var(--foam)" />
                                                    <stop offset="12%" stop-color="var(--beer)" />
                                                    <stop offset="100%" stop-color="var(--beer-deep)" />
                                                </linearGradient>
                                                <clipPath id="wicket-beer-clip">
                                                    <path d="M24 20 L28 96 Q40 106 52 96 L56 20 Z" />
                                                </clipPath>
                                            </defs>
                                            <path
                                                d="M22 16 L26 100 Q40 110 54 100 L58 16"
                                                fill="color-mix(in oklab, var(--color-base-content) 8%, transparent)"
                                            />
                                            <g clip-path="url(#wicket-beer-clip)">
                                                <g class="wicket-beer-liquid">
                                                    <rect x="20" y="16" width="40" height="92" fill="url(#wicket-beer-grad)" />
                                                    <ellipse class="wicket-beer-foam" cx="40" cy="22" rx="16" ry="6" fill="var(--foam)" />
                                                    <g class="wicket-beer-bubbles" fill="var(--foam)">
                                                        <circle cx="32" cy="70" r="1.6" />
                                                        <circle cx="44" cy="58" r="1.2" />
                                                        <circle cx="36" cy="46" r="1.4" />
                                                        <circle cx="48" cy="78" r="1.1" />
                                                    </g>
                                                </g>
                                            </g>
                                            <path
                                                d="M22 16 L26 100 Q40 110 54 100 L58 16"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="3"
                                                stroke-linejoin="round"
                                            />
                                            <path
                                                d="M18 16 H62"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="4"
                                                stroke-linecap="round"
                                            />
                                            <path
                                                d="M28 104 H52 L50 108 H30 Z"
                                                fill="currentColor"
                                                opacity="0.85"
                                            />
                                        </svg>
                                        <label class="sr-only" for="sip-count">Sips</label>
                                        <input
                                            id="sip-count"
                                            class="sr-only"
                                            type="number"
                                            name="sips"
                                            min="0"
                                            max="{{ $maxSipFine }}"
                                            value="{{ $selectedSipCount }}"
                                            data-sip-count
                                            inputmode="numeric"
                                        >
                                        <p class="text-sm font-medium tabular-nums text-base-content/70" data-sip-label>
                                            {{ $selectedSipCount === 1 ? '1 sip' : $selectedSipCount.' sips' }}
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        class="btn btn-circle btn-lg"
                                        data-sip-inc
                                        aria-label="More sips"
                                        @disabled($selectedSipCount >= $maxSipFine)
                                    >
                                        +
                                    </button>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="fieldset">
                            <legend class="label">Specials</legend>
                            <div class="grid grid-cols-3 items-stretch gap-2">
                                @foreach ($fineTypes as $type)
                                    <label class="cursor-pointer">
                                        <input
                                            type="radio"
                                            name="punishment"
                                            value="{{ $type->value }}"
                                            class="peer sr-only"
                                            data-sip-special
                                            @checked(old('punishment') === $type->value || old('type') === $type->value)
                                        >
                                        <span class="btn btn-lg flex h-24 w-full flex-col items-center justify-center gap-1 px-1 py-2 peer-checked:btn-primary">
                                            <span class="text-xl leading-none">{{ $type->emoji() }}</span>
                                            <span class="text-center text-xs leading-tight whitespace-nowrap sm:text-sm">{{ $type->label() }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <fieldset class="fieldset">
                            <label class="label" for="reason">Reason</label>
                            <textarea
                                id="reason"
                                name="reason"
                                rows="3"
                                maxlength="500"
                                required
                                placeholder="Forgot their whites"
                                class="textarea w-full @error('reason') textarea-error @enderror"
                            >{{ old('reason') }}</textarea>
                        </fieldset>

                        <button type="submit" class="btn btn-primary btn-lg w-full">Give fine</button>
                    </form>
                </div>
            </div>
        </div>

        <input
            type="radio"
            name="wickets_tabs"
            class="tab grow"
            aria-label="Drink"
            @checked($tab === 'drink')
        >
        <div class="tab-content mt-4 space-y-3">
            @if ($errors->has('sips'))
                <div role="alert" class="alert alert-error">
                    <span>{{ $errors->first('sips') }}</span>
                </div>
            @endif

            <div class="space-y-3" data-live-region="drink">
                @include('wickets.drink')
            </div>
        </div>

        <input
            type="radio"
            name="wickets_tabs"
            class="tab grow"
            aria-label="People"
            @checked($tab === 'people')
        >
        <div class="tab-content mt-4 space-y-3 overflow-visible">
            @if ($errors->has('user_ids') || $errors->has('user') || $errors->has('role') || $errors->has('is_tournament') || $errors->has('name'))
                <div role="alert" class="alert alert-error">
                    <span>{{ $errors->first('user_ids') ?: $errors->first('user') ?: $errors->first('role') ?: $errors->first('is_tournament') ?: $errors->first('name') }}</span>
                </div>
            @endif

            <section class="card bg-base-100 shadow-xl">
                <div class="card-body gap-3 p-4 sm:p-5">
                    <h2 class="card-title">Players</h2>
                    <ul class="divide-y divide-base-300">
                        @foreach ($group->users as $member)
                            <li class="flex flex-wrap items-center gap-3 py-3 first:pt-0 last:pb-0">
                                <span class="inline-block h-3 w-3 shrink-0 rounded-full" style="background: {{ $member->boardColor() }}"></span>
                                <p class="min-w-0 flex-1 truncate font-medium">
                                    {{ $member->name }}
                                    @if ($group->isOwnedBy($member))
                                        <span class="font-normal text-base-content/50">owner</span>
                                    @endif
                                    @if ($group->isFinesMaster($member))
                                        <span class="font-normal text-base-content/50">fines master</span>
                                    @endif
                                </p>
                                @if ($isOwner && ! $group->isOwnedBy($member))
                                    <div class="flex shrink-0 items-center gap-1">
                                        <form method="POST" action="{{ route('wickets.members.update', [$group, $member]) }}">
                                            @csrf
                                            @method('PATCH')
                                            @if ($group->isFinesMaster($member))
                                                <input type="hidden" name="role" value="{{ \App\Enums\WicketGroupRole::Member->value }}">
                                                <button type="submit" class="btn btn-ghost btn-sm">Remove role</button>
                                            @else
                                                <input type="hidden" name="role" value="{{ \App\Enums\WicketGroupRole::FinesMaster->value }}">
                                                <button type="submit" class="btn btn-ghost btn-sm">Make Fines Master</button>
                                            @endif
                                        </form>
                                        <form
                                            method="POST"
                                            action="{{ route('wickets.members.destroy', [$group, $member]) }}"
                                            onsubmit="return confirm('Remove this player from the group?')"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-sm">Remove</button>
                                        </form>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            <section class="card overflow-visible bg-base-100 shadow-xl">
                <div class="card-body gap-4 overflow-visible p-4 sm:p-5">
                    <div>
                        <h2 class="card-title">Add players</h2>
                        <p class="text-sm text-base-content/70">Anyone on Spice Rules can be added to this group.</p>
                    </div>

                    @if ($availableUsers->isEmpty())
                        <p class="text-sm text-base-content/70">Everyone on Spice Rules is already in this group.</p>
                    @else
                        <form method="POST" action="{{ route('wickets.members.store', $group) }}" class="space-y-4">
                            @csrf
                            <fieldset class="fieldset">
                                <legend class="label">People</legend>
                                <p class="mb-2 text-sm text-base-content/70">Search and pick one or more players.</p>
                                @include('wickets.people-picker', [
                                    'pickerId' => 'members',
                                    'people' => $availableUsers,
                                    'inputName' => 'user_ids[]',
                                    'selectedIds' => collect(old('user_ids', [])),
                                ])
                            </fieldset>
                            <button type="submit" class="btn btn-primary btn-lg w-full">Add to group</button>
                        </form>
                    @endif
                </div>
            </section>

            @if ($isOwner)
                <section class="card bg-base-100 shadow-xl">
                    <div class="card-body gap-4 p-4 sm:p-5">
                        <div class="min-w-0">
                            <h2 class="card-title whitespace-normal">Group settings</h2>
                            <p class="mt-1 text-sm whitespace-normal text-base-content/70">These apply to everyone in this group.</p>
                        </div>
                        <form method="POST" action="{{ route('wickets.update', $group) }}" class="space-y-4">
                            @csrf
                            @method('PATCH')
                            <div class="flex w-full min-w-0 items-start gap-3">
                                <input type="hidden" name="is_tournament" value="0">
                                <input
                                    id="is_tournament"
                                    type="checkbox"
                                    name="is_tournament"
                                    value="1"
                                    class="toggle toggle-primary mt-0.5 shrink-0"
                                    @checked(old('is_tournament', $group->is_tournament))
                                >
                                <label for="is_tournament" class="min-w-0 flex-1 cursor-pointer">
                                    <span class="font-medium">Tournament mode</span>
                                    <span class="mt-0.5 block text-sm font-normal whitespace-normal text-base-content/70">
                                        Players can't see their own fines — only what they gave.
                                    </span>
                                </label>
                            </div>
                            <div class="flex w-full min-w-0 items-start gap-3">
                                <input type="hidden" name="notify_all_on_fine" value="0">
                                <input
                                    id="notify_all_on_fine"
                                    type="checkbox"
                                    name="notify_all_on_fine"
                                    value="1"
                                    class="toggle toggle-primary mt-0.5 shrink-0"
                                    @checked(old('notify_all_on_fine', $group->notify_all_on_fine))
                                >
                                <label for="notify_all_on_fine" class="min-w-0 flex-1 cursor-pointer">
                                    <span class="font-medium">Notify the group</span>
                                    <span class="mt-0.5 block text-sm font-normal whitespace-normal text-base-content/70">
                                        Everyone except the person giving the fine gets a ping, like “Alex fined Sam 2 sips for being late.” The person fined still gets their usual notification.
                                    </span>
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </form>
                    </div>
                </section>
            @endif

            @if ($isOwner)
                <section class="card bg-base-100 shadow-xl">
                    <div class="card-body gap-4 p-4 sm:p-5">
                        <div class="min-w-0">
                            <h2 class="card-title whitespace-normal">Delete group</h2>
                            <p class="mt-1 text-sm whitespace-normal text-base-content/70">This cannot be undone from the app. The group will disappear for everyone in it.</p>
                        </div>
                        <div role="alert" class="alert alert-warning">
                            <span class="min-w-0 whitespace-normal">Type <span class="font-semibold">{{ $group->name }}</span> to confirm. Fines and history stay on file, but nobody will see this group.</span>
                        </div>
                        <form method="POST" action="{{ route('wickets.destroy', $group) }}" class="space-y-4">
                            @csrf
                            @method('DELETE')
                            <fieldset class="fieldset">
                                <legend class="label">Group name</legend>
                                <input
                                    type="text"
                                    name="name"
                                    value="{{ old('name') }}"
                                    class="input input-bordered w-full"
                                    autocomplete="off"
                                    required
                                >
                            </fieldset>
                            <button type="submit" class="btn btn-error w-full sm:w-auto">Delete group</button>
                        </form>
                    </div>
                </section>
            @endif
        </div>
        </div>
    </div>
@endsection
