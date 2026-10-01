@php
    $you = $you ?? ['hasData' => false];
    $practiceStats = $practiceStats ?? [
        'hasData' => false,
        'inProgress' => false,
        'stats' => [
            'played' => 0,
            'wins' => 0,
            'winPercent' => 0,
            'averageGuesses' => null,
            'averageDurationMs' => null,
            'averageDurationLabel' => null,
            'missedMoreThanOnce' => 0,
        ],
        'pace' => [
            'available' => false,
            'fasterThanPercent' => null,
            'band' => null,
            'summary' => null,
            'sampleSize' => 0,
        ],
        'struggleWords' => [],
    ];
@endphp

@if (! $you['hasData'])
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title">You</h2>
            <p class="text-base-content/70">Finish today's Spirdle to start your fastest times and streaks.</p>
        </div>
    </div>
@else
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <section class="card bg-base-100 shadow-md">
            <div class="card-body justify-center gap-1 p-4 text-center">
                <p class="text-4xl font-bold tabular-nums leading-none text-primary sm:text-5xl">{{ $you['stats']['fastestLabel'] ?? '—' }}</p>
                <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-base-content/50">Fastest win</p>
            </div>
        </section>
        <section class="card bg-base-100 shadow-md">
            <div class="card-body justify-center gap-1 p-4 text-center">
                <p class="text-4xl font-bold tabular-nums leading-none text-primary sm:text-5xl">{{ $you['stats']['averageGuesses'] ?? '—' }}</p>
                <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-base-content/50">Avg guesses</p>
            </div>
        </section>
        <section class="card bg-base-100 shadow-md">
            <div class="card-body justify-center gap-1 p-4 text-center">
                <p class="text-4xl font-bold tabular-nums leading-none text-primary sm:text-5xl">{{ $you['stats']['currentStreak'] }}</p>
                <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-base-content/50">Current streak</p>
            </div>
        </section>
        <section class="card bg-base-100 shadow-md">
            <div class="card-body justify-center gap-1 p-4 text-center">
                <p class="text-4xl font-bold tabular-nums leading-none text-primary sm:text-5xl">{{ $you['stats']['winPercent'] }}%</p>
                <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-base-content/50">
                    {{ $you['stats']['wins'] }}/{{ $you['stats']['played'] }} won · best streak {{ $you['stats']['longestStreak'] }}
                </p>
            </div>
        </section>
    </div>

    <section class="card bg-base-100 shadow-md">
        <div class="card-body gap-3 p-4">
            <h2 class="text-sm font-semibold leading-tight">Guess spread</h2>
            <p class="text-xs text-base-content/55">How often you land it in 1–6, or miss.</p>
            <ol class="space-y-1.5">
                @foreach ($you['distribution'] as $row)
                    <li class="grid grid-cols-[1.5rem_minmax(0,1fr)_2rem] items-center gap-2 text-sm">
                        <span class="tabular-nums font-semibold">{{ $row['guesses'] }}</span>
                        <div class="h-5 overflow-hidden rounded-md bg-base-200">
                            <div
                                class="h-full rounded-md bg-primary transition-[width] duration-300"
                                style="width: {{ max($row['percent'], $row['count'] > 0 ? 8 : 0) }}%"
                            ></div>
                        </div>
                        <span class="text-right tabular-nums text-base-content/70">{{ $row['count'] }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="card bg-base-100 shadow-md">
        <div class="card-body gap-3 p-4">
            <h2 class="text-sm font-semibold leading-tight">Fastest wins</h2>
            <p class="text-xs text-base-content/55">Your quickest solves, fastest first.</p>
            @if ($you['fastest'] === [])
                <p class="text-sm text-base-content/55">No wins yet.</p>
            @else
                <ol class="space-y-2">
                    @foreach ($you['fastest'] as $index => $row)
                        <li class="flex items-center gap-3 rounded-xl bg-base-200 px-3 py-2">
                            <span class="w-6 text-sm font-bold tabular-nums text-base-content/50">{{ $index + 1 }}</span>
                            <span class="min-w-0 flex-1 truncate font-medium">{{ $row['label'] }}</span>
                            <span class="tabular-nums text-sm">{{ $row['guesses'] }}g</span>
                            <span class="tabular-nums font-semibold">{{ $row['duration'] }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </section>
@endif

<section class="card bg-base-100 shadow-xl mt-4">
    <div class="card-body gap-3 p-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="card-title text-base">Practice</h2>
                <p class="text-sm text-base-content/70">Warm-ups stay off the daily board, weekly standings, and streaks.</p>
            </div>
            <a href="{{ route('spirdle.practice') }}" class="btn btn-sm btn-primary shrink-0">
                {{ ! empty($practiceStats['inProgress']) ? 'Continue' : 'Practice' }}
            </a>
        </div>
        @if (! $practiceStats['hasData'])
            <p class="text-sm text-base-content/55">Finish a practice round to start this tally.</p>
        @else
            <dl class="grid grid-cols-2 gap-3 text-center sm:grid-cols-3 lg:grid-cols-5">
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-base-content/50">Rounds</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums">{{ $practiceStats['stats']['played'] }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-base-content/50">Won</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums">{{ $practiceStats['stats']['wins'] }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-base-content/50">Avg guesses</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums">{{ $practiceStats['stats']['averageGuesses'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-base-content/50">Avg time</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums">{{ $practiceStats['stats']['averageDurationLabel'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-base-content/50">Missed 2×+</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums">{{ $practiceStats['stats']['missedMoreThanOnce'] }}</dd>
                </div>
            </dl>

            @if (! empty($practiceStats['pace']['available']))
                <div class="rounded-xl bg-base-200 px-3 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-base-content/50">Pace vs others</p>
                    <p class="mt-1 text-lg font-semibold leading-tight">
                        {{ $practiceStats['pace']['band'] }}
                        <span class="font-normal text-base-content/70">· faster than {{ $practiceStats['pace']['fasterThanPercent'] }}%</span>
                    </p>
                    <p class="mt-1 text-sm text-base-content/60">{{ $practiceStats['pace']['summary'] }}</p>
                </div>
            @elseif ($practiceStats['stats']['averageDurationMs'] !== null)
                <p class="text-sm text-base-content/55">Pace ranking unlocks once a few more players finish practice rounds.</p>
            @endif

            <div>
                <h3 class="text-sm font-semibold leading-tight">Words you struggle with</h3>
                <p class="mt-0.5 text-xs text-base-content/55">Misses and 5–6 guess solves from practice.</p>
                @if ($practiceStats['struggleWords'] === [])
                    <p class="mt-2 text-sm text-base-content/55">No tough practice words yet — keep going.</p>
                @else
                    <ol class="mt-2 space-y-2">
                        @foreach ($practiceStats['struggleWords'] as $row)
                            <li class="flex items-center gap-3 rounded-xl bg-base-200 px-3 py-2">
                                <span class="min-w-0 flex-1 truncate font-semibold tracking-wide">{{ $row['word'] }}</span>
                                @if (($row['missCount'] ?? 0) > 1)
                                    <span class="badge badge-sm badge-warning">{{ $row['missCount'] }}× missed</span>
                                @endif
                                <span class="text-sm tabular-nums text-base-content/70">
                                    {{ $row['won'] ? $row['guesses'].'g' : 'Missed' }}
                                </span>
                                @if ($row['duration'])
                                    <span class="tabular-nums font-semibold">{{ $row['duration'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        @endif
    </div>
</section>

<div
    class="mt-4"
    data-practice-results
    data-practice-results-url="{{ route('spirdle.practice.results', absolute: false) }}"
>
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <p class="text-base-content/70">Loading practice grids…</p>
        </div>
    </div>
</div>
