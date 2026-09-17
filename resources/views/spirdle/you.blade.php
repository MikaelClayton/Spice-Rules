@php
    $you = $you ?? ['hasData' => false];
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
