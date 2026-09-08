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
    } elseif ($errors->hasAny(['user_ids', 'user', 'role', 'is_tournament'])) {
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

    <section class="card bg-base-100 shadow-xl mb-5">
        <div class="card-body gap-3 p-4 sm:p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">You owe</p>
            <dl
                @class([
                    'grid gap-3',
                    'grid-cols-1' => $mySpecialCounts->isEmpty(),
                    'grid-cols-2' => $mySpecialCounts->isNotEmpty(),
                    'sm:grid-cols-3' => $mySpecialCounts->count() === 2,
                    'sm:grid-cols-4' => $mySpecialCounts->count() >= 3,
                ])
            >
                <div class="rounded-box bg-base-200 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-base-content/50">🍺 Sips</dt>
                    <dd class="mt-1 text-3xl font-bold leading-none tabular-nums">{{ $hideOwnFines ? '?' : $myRemainingSips }}</dd>
                </div>
                @foreach ($mySpecialCounts as $special)
                    <div class="rounded-box bg-base-200 p-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-base-content/50">
                            {{ $special['type']->emoji() }} {{ $special['type']->label() }}
                        </dt>
                        <dd class="mt-1 text-3xl font-bold leading-none tabular-nums">{{ $special['count'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    <div class="tabs tabs-box w-full">
        <input
            type="radio"
            name="wickets_tabs"
            class="tab grow"
            aria-label="Board"
            @checked($tab === 'board')
        >
        <div class="tab-content mt-4 space-y-3">
            @forelse ($standings as $row)
                <details class="card group bg-base-100 shadow-md {{ $row['user']->id === Auth::id() ? 'ring-2 ring-primary' : '' }}">
                    <summary class="card-body cursor-pointer list-none p-3.5 sm:p-4 [&::-webkit-details-marker]:hidden">
                        <div class="flex items-center gap-3">
                            <span class="inline-block h-3 w-3 shrink-0 rounded-full" style="background: {{ $row['user']->boardColor() }}"></span>
                            <p class="min-w-0 flex-1 truncate font-semibold leading-tight">
                                {{ $row['user']->name }}
                            </p>
                            <div class="flex shrink-0 items-center gap-3">
                                @foreach ($row['specials'] as $special)
                                    @include('wickets.stat', [
                                        'emoji' => $special['type']->emoji(),
                                        'count' => $special['count'],
                                        'label' => $special['type']->label(),
                                        'type' => $special['type']->value,
                                        'at_least' => $special['at_least'] ?? false,
                                    ])
                                @endforeach
                                @if ($row['showSips'])
                                    @include('wickets.stat', [
                                        'emoji' => '🍺',
                                        'count' => $row['sips'],
                                        'label' => $row['sips'] === 1 ? 'sip' : 'sips',
                                        'type' => 'sips',
                                        'at_least' => $row['sips_at_least'] ?? false,
                                    ])
                                @endif
                            </div>
                            <span class="shrink-0 text-base-content/40 transition group-open:rotate-180" aria-hidden="true">▾</span>
                        </div>
                    </summary>
                    <div class="card-body border-t border-base-300 px-5 py-4 sm:px-6" data-player-fines="{{ $row['user']->id }}">
                        @if ($row['finesHidden'])
                            <p class="text-sm text-base-content/60">Your fines are hidden.</p>
                        @else
                            @forelse ($row['fines'] as $fine)
                                <div class="flex items-center justify-between gap-3 border-t border-base-300 py-3 first:border-t-0 first:pt-0 last:pb-0" data-open-fine-id="{{ $fine->id }}">
                                    <p class="min-w-0 text-sm text-base-content/70">{{ $fine->reason }}</p>
                                    @include('wickets.stat', [
                                        'emoji' => $fine->type->emoji(),
                                        'count' => $fine->type->isSip() ? $fine->remainingSips() : 1,
                                        'label' => $fine->type->isSip()
                                            ? ($fine->remainingSips() === 1 ? 'sip' : 'sips')
                                            : $fine->type->label(),
                                        'type' => $fine->type->value,
                                    ])
                                </div>
                            @empty
                                <p class="text-sm text-base-content/60">No open fines.</p>
                            @endforelse
                        @endif
                    </div>
                </details>
            @empty
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title">Nobody here yet</h2>
                        <p class="text-base-content/70">Add players on the People tab.</p>
                    </div>
                </div>
            @endforelse

            <section class="card bg-base-100 shadow-xl">
                <div class="card-body p-4">
                    <h2 class="card-title text-base">Activity</h2>
                    @forelse ($activity as $item)
                        @if ($item['kind'] === 'drink')
                            <div class="flex items-center justify-between gap-3 border-t border-base-300 py-3 first:border-t-0 first:pt-1">
                                <div class="min-w-0">
                                    <p class="font-semibold leading-tight">{{ $item['log']->user?->name ?? 'Unknown' }}</p>
                                    <p class="mt-0.5 text-sm text-base-content/70">
                                        Drank {{ $item['log']->sips === 1 ? '1 sip' : $item['log']->sips.' sips' }}
                                    </p>
                                    <p class="mt-1 text-xs text-base-content/50">{{ $item['occurred_at']?->diffForHumans() }}</p>
                                </div>
                                @include('wickets.stat', [
                                    'emoji' => '🍺',
                                    'count' => $item['log']->sips,
                                    'label' => $item['log']->sips === 1 ? 'sip' : 'sips',
                                    'type' => 'sips',
                                ])
                            </div>
                        @elseif ($item['kind'] === 'special_done')
                            <div class="flex items-center justify-between gap-3 border-t border-base-300 py-3 first:border-t-0 first:pt-1">
                                <div class="min-w-0">
                                    <p class="font-semibold leading-tight">{{ $item['fine']->issuedTo?->name ?? 'Unknown' }}</p>
                                    <p class="mt-0.5 text-sm text-base-content/70">{{ $item['fine']->reason }}</p>
                                    <p class="mt-1 text-xs text-base-content/50">Done · {{ $item['occurred_at']?->diffForHumans() }}</p>
                                </div>
                                @include('wickets.stat', [
                                    'emoji' => $item['fine']->type->emoji(),
                                    'count' => 1,
                                    'label' => $item['fine']->type->label(),
                                    'type' => $item['fine']->type->value,
                                ])
                            </div>
                        @else
                            <div class="flex items-center justify-between gap-3 border-t border-base-300 py-3 first:border-t-0 first:pt-1">
                                <div class="min-w-0">
                                    <p class="font-semibold leading-tight">
                                        {{ $item['fine']->issuedTo?->name ?? 'Unknown' }}
                                        <span class="font-medium text-base-content/50">from {{ $item['fine']->issuedBy?->name ?? 'Unknown' }}</span>
                                    </p>
                                    <p class="mt-0.5 text-sm text-base-content/70">{{ $item['fine']->reason }}</p>
                                    <p class="mt-1 text-xs text-base-content/50">{{ $item['occurred_at']?->diffForHumans() }}</p>
                                </div>
                                @include('wickets.stat', [
                                    'emoji' => $item['fine']->type->emoji(),
                                    'count' => $item['fine']->type->isSip() ? $item['fine']->sips_owed : 1,
                                    'label' => $item['fine']->type->isSip()
                                        ? ($item['fine']->sips_owed === 1 ? 'sip' : 'sips')
                                        : $item['fine']->type->label(),
                                    'type' => $item['fine']->type->value,
                                ])
                            </div>
                        @endif
                    @empty
                        <p class="text-sm text-base-content/70">Nothing yet. Someone's about to have a big night.</p>
                    @endforelse
                </div>
            </section>
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
                            @php
                                $selectedMemberIds = collect(old('issued_to_user_ids', old('issued_to_user_id') ? [old('issued_to_user_id')] : []))
                                    ->map(fn ($id): int => (int) $id);
                            @endphp
                            <div data-people-picker class="relative">
                                <div class="flex min-h-12 flex-wrap items-center gap-1.5 rounded-field border border-base-300 bg-base-100 px-2 py-1.5">
                                    <div data-people-chips class="flex flex-wrap gap-1.5 empty:hidden">
                                        @foreach ($group->users as $member)
                                            @if ($selectedMemberIds->contains($member->id))
                                                <span class="badge badge-secondary max-w-[10rem] gap-1">
                                                    <span class="truncate">{{ $member->name }}</span>
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                    <input
                                        id="wicket-people-search"
                                        type="search"
                                        data-people-search
                                        autocomplete="off"
                                        autocorrect="off"
                                        spellcheck="false"
                                        role="combobox"
                                        aria-autocomplete="list"
                                        aria-controls="wicket-people-options"
                                        aria-expanded="false"
                                        aria-haspopup="listbox"
                                        placeholder="Search players…"
                                        class="min-h-9 min-w-[8rem] flex-1 bg-transparent px-1 text-base outline-none placeholder:text-base-content/40"
                                    >
                                </div>
                                <ul
                                    id="wicket-people-options"
                                    data-people-list
                                    role="listbox"
                                    class="absolute z-40 mt-1 max-h-60 w-full overflow-y-auto rounded-box border border-base-300 bg-base-100 p-1 shadow-lg"
                                >
                                    @foreach ($group->users as $member)
                                        <li
                                            data-people-option
                                            data-name="{{ str($member->name)->lower() }}"
                                            data-label="{{ $member->name }}"
                                            role="option"
                                        >
                                            <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-base-200 has-[:checked]:bg-base-200">
                                                <input
                                                    type="checkbox"
                                                    name="issued_to_user_ids[]"
                                                    value="{{ $member->id }}"
                                                    class="checkbox checkbox-primary checkbox-sm"
                                                    @checked($selectedMemberIds->contains($member->id))
                                                >
                                                <span class="inline-block h-3 w-3 shrink-0 rounded-full" style="background: {{ $member->boardColor() }}"></span>
                                                <span class="min-w-0 flex-1 truncate font-medium">{{ $member->name }}</span>
                                            </label>
                                        </li>
                                    @endforeach
                                    <li data-people-empty hidden class="px-3 py-2 text-sm text-base-content/60">No matching players</li>
                                </ul>
                            </div>
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

            <section class="card bg-base-100 shadow-xl">
                <div class="card-body gap-4 p-4 sm:p-5">
                    <div>
                        <h2 class="card-title">Drink sips</h2>
                        <p class="text-sm text-base-content/70">Log what you just drank to knock sips off your fines.</p>
                    </div>

                    @if ($hideOwnFines)
                        <p class="text-sm text-base-content/70">Your sip fines are hidden in this tournament.</p>
                        <form method="POST" action="{{ route('wickets.sips.store', $group) }}" class="space-y-2">
                            @csrf
                            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">How many sips?</p>
                            <div class="grid grid-cols-4 gap-2">
                                @foreach ([1, 2, 3, 4] as $count)
                                    <button type="submit" name="sips" value="{{ $count }}" class="btn btn-lg h-16 text-xl tabular-nums">
                                        {{ $count }}
                                    </button>
                                @endforeach
                            </div>
                        </form>
                    @elseif ($mySipFines->isNotEmpty())
                        <ul class="space-y-2">
                            @foreach ($mySipFines as $fine)
                                <li class="rounded-xl bg-base-200 px-3 py-2.5">
                                    <p class="font-semibold tabular-nums">{{ $fine->remainingSips() }} {{ $fine->remainingSips() === 1 ? 'sip' : 'sips' }}</p>
                                    <p class="text-sm text-base-content/70">{{ $fine->reason }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @unless ($hideOwnFines)
                        @if ($myRemainingSips > 0)
                            <form method="POST" action="{{ route('wickets.sips.store', $group) }}" class="space-y-2">
                                @csrf
                                <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">How many sips?</p>
                                <div class="grid grid-cols-4 gap-2">
                                    @foreach ([1, 2, 3, 4] as $count)
                                        @if ($count <= $myRemainingSips)
                                            <button type="submit" name="sips" value="{{ $count }}" class="btn btn-lg h-16 text-xl tabular-nums">
                                                {{ $count }}
                                            </button>
                                        @endif
                                    @endforeach
                                </div>
                                @if ($myRemainingSips > 4)
                                    <button type="submit" name="sips" value="{{ $myRemainingSips }}" class="btn btn-primary btn-lg w-full">
                                        Drink the rest ({{ $myRemainingSips }})
                                    </button>
                                @endif
                            </form>
                        @elseif ($mySpecials->isNotEmpty())
                            <p class="text-sm text-base-content/70">No sip fines left. Specials still count until you mark them done.</p>
                        @else
                            <p class="text-sm text-base-content/70">You're clear. For now.</p>
                        @endif
                    @endunless
                </div>
            </section>

            <section class="card bg-base-100 shadow-xl">
                <div class="card-body gap-3 p-4 sm:p-5">
                    <div>
                        <h2 class="card-title">Specials</h2>
                        <p class="text-sm text-base-content/70">Down downs, funnels, and shoeys get ticked off here.</p>
                    </div>

                    @if ($hideOwnFines)
                        <p class="text-sm text-base-content/70">Your specials are hidden in this tournament.</p>
                    @else
                        @forelse ($mySpecials as $special)
                            <article class="rounded-xl bg-base-200 p-3.5">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-semibold">{{ $special->type->emoji() }} {{ $special->displayLabel() }}</p>
                                        <p class="mt-0.5 text-sm text-base-content/70">{{ $special->reason }}</p>
                                    </div>
                                    <form method="POST" action="{{ route('wickets.fines.completions.store', [$group, $special]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-primary">Done</button>
                                    </form>
                                </div>
                            </article>
                        @empty
                            <p class="text-sm text-base-content/70">No down downs, funnels, or shoeys outstanding.</p>
                        @endforelse
                    @endif
                </div>
            </section>
        </div>

        <input
            type="radio"
            name="wickets_tabs"
            class="tab grow"
            aria-label="People"
            @checked($tab === 'people')
        >
        <div class="tab-content mt-4 space-y-3">
            @if ($errors->has('user_ids') || $errors->has('user') || $errors->has('role') || $errors->has('is_tournament'))
                <div role="alert" class="alert alert-error">
                    <span>{{ $errors->first('user_ids') ?: $errors->first('user') ?: $errors->first('role') ?: $errors->first('is_tournament') }}</span>
                </div>
            @endif

            @if ($isOwner)
                <section class="card bg-base-100 shadow-xl">
                    <div class="card-body gap-4 p-4 sm:p-5">
                        <div>
                            <h2 class="card-title">Tournament</h2>
                            <p class="text-sm text-base-content/70">Players can't see their own fines — only what they gave.</p>
                        </div>
                        <form method="POST" action="{{ route('wickets.update', $group) }}" class="space-y-3">
                            @csrf
                            @method('PATCH')
                            <label class="label cursor-pointer justify-start gap-3">
                                <input type="hidden" name="is_tournament" value="0">
                                <input
                                    type="checkbox"
                                    name="is_tournament"
                                    value="1"
                                    class="toggle toggle-primary"
                                    @checked(old('is_tournament', $group->is_tournament))
                                >
                                <span class="font-medium">Tournament mode</span>
                            </label>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </form>
                    </div>
                </section>
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

            <section class="card bg-base-100 shadow-xl">
                <div class="card-body gap-4 p-4 sm:p-5">
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
                                <div class="max-h-80 space-y-1 overflow-y-auto">
                                    @foreach ($availableUsers as $person)
                                        <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg px-2 py-2 hover:bg-base-200">
                                            <input
                                                type="checkbox"
                                                name="user_ids[]"
                                                value="{{ $person->id }}"
                                                class="checkbox checkbox-primary"
                                                @checked(collect(old('user_ids', []))->map(fn ($id): int => (int) $id)->contains($person->id))
                                            >
                                            <span class="inline-block h-3 w-3 shrink-0 rounded-full" style="background: {{ $person->boardColor() }}"></span>
                                            <span class="min-w-0 flex-1 truncate font-medium">{{ $person->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <button type="submit" class="btn btn-primary btn-lg w-full">Add to group</button>
                        </form>
                    @endif
                </div>
            </section>
        </div>
    </div>
@endsection
