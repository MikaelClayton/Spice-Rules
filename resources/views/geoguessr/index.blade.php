@extends('layouts.app')

@section('title', 'GeoGuessr — '.config('app.name'))

@section('content')
    <div class="mb-4">
        <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm -ml-2">← Back</a>
    </div>

    <div class="mb-5">
        <h1 class="text-2xl font-bold sm:text-3xl">GeoGuessr</h1>
    </div>

    @if (session('status'))
        <div role="alert" class="alert alert-success mb-5">
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (($progress['level'] ?? null) || ($progress['xp'] ?? null))
        <section class="card bg-base-100 shadow-xl mb-5">
            <div class="card-body gap-3 p-4 sm:p-5">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Level</p>
                        <p class="text-4xl font-bold tabular-nums leading-none">{{ $progress['level'] }}</p>
                    </div>
                    <div class="sm:text-right">
                        @if (isset($progress['nextLevel']))
                            <p class="text-sm text-base-content/60">Next level {{ $progress['nextLevel'] }}</p>
                        @endif
                        <p class="text-lg font-semibold tabular-nums">
                            {{ isset($progress['xp']) ? number_format($progress['xp']) : '—' }}
                            @if (isset($progress['nextLevelXp']))
                                <span class="font-medium text-base-content/50">/</span>
                                {{ number_format($progress['nextLevelXp']) }}
                            @endif
                            XP
                        </p>
                    </div>
                </div>
                @if (isset($progress['percent']))
                    <progress class="progress progress-primary w-full" value="{{ $progress['percent'] }}" max="100"></progress>
                @endif
            </div>
        </section>
    @endif

    <div class="tabs tabs-box w-full sm:tabs-lg" data-geoguessr-board>
        <input
            type="radio"
            name="geoguessr_tabs"
            class="tab grow"
            aria-label="Today"
            data-tab="today"
            @checked($activeTab === 'today')
        >
        <div class="tab-content mt-4 space-y-4">
            @if ($results->isEmpty())
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title">Today's results</h2>
                        <p class="text-base-content/70">Nobody has logged a score yet today.</p>
                    </div>
                </div>
            @else
                <ol class="space-y-2.5 sm:space-y-3">
                    @foreach ($results as $index => $result)
                        @php
                            $place = $ranks[$result->id] ?? ($index + 1);
                            $isYou = $result->geoguesser?->user_id === Auth::id();
                            $name = $result->geoguesser?->user?->name ?? $result->geoguesser?->username;
                            $nick = $result->geoguesser?->username;
                            $rewards = [];

                            if ($result->is_done_as_team) {
                                $rewards[] = [
                                    'emoji' => '🤝',
                                    'label' => 'Played as a team',
                                    'message' => '🤝 Played as a team',
                                ];
                            } else {
                                if ($closestDistance !== null && $result->total_distance === $closestDistance) {
                                    $rewards[] = [
                                        'emoji' => '💪',
                                        'label' => 'Closest to target',
                                        'message' => '💪 Closest to target · '.number_format($result->total_distance / 1000, 1).' km',
                                    ];
                                }

                                if ($furthestDistance !== null && $result->total_distance === $furthestDistance) {
                                    $rewards[] = [
                                        'emoji' => '💩',
                                        'label' => 'Furthest from target',
                                        'message' => '💩 Furthest from target · '.number_format($result->total_distance / 1000, 1).' km',
                                    ];
                                }

                                if ($fewestSteps !== null && $result->total_steps_count === $fewestSteps) {
                                    $rewards[] = [
                                        'emoji' => '♿',
                                        'label' => 'Least steps',
                                        'message' => '♿ Least steps · '.number_format($result->total_steps_count),
                                    ];
                                }

                                if ($mostSteps !== null && $result->total_steps_count === $mostSteps) {
                                    $rewards[] = [
                                        'emoji' => '🏃',
                                        'label' => 'Most steps',
                                        'message' => '🏃 Most steps · '.number_format($result->total_steps_count),
                                    ];
                                }
                            }
                        @endphp
                        <li class="card bg-base-100 shadow-md {{ $isYou ? 'ring-2 ring-primary' : '' }}">
                            <div class="card-body p-3.5 sm:p-4">
                                <div class="flex items-start gap-3">
                                    <span @class([
                                        'badge badge-md sm:badge-lg mt-0.5 shrink-0 tabular-nums',
                                        'badge-warning' => $place === 1,
                                        'badge-ghost' => $place === 2,
                                        'badge-accent' => $place === 3,
                                        'badge-neutral' => $place > 3,
                                    ])>{{ $place }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex min-w-0 items-center gap-1">
                                            <p class="truncate font-semibold leading-tight">{{ $name }}</p>
                                            @foreach ($rewards as $reward)
                                                <button
                                                    type="button"
                                                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-base leading-none hover:bg-base-200 active:scale-95"
                                                    data-reward="{{ $reward['message'] }}"
                                                    aria-label="{{ $reward['label'] }}"
                                                >{{ $reward['emoji'] }}</button>
                                            @endforeach
                                        </div>
                                        @if ($nick && $nick !== $name)
                                            <p class="mt-0.5 truncate text-xs text-base-content/55">{{ $nick }}</p>
                                        @endif
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <p class="text-lg font-bold leading-none tabular-nums sm:text-xl">{{ number_format($result->total_score) }}</p>
                                        <p class="mt-1.5 text-xs tabular-nums text-base-content/60">
                                            @if ($result->total_distance)
                                                {{ number_format($result->total_distance / 1000, 1) }} km
                                            @else
                                                Distance pending
                                            @endif
                                        </p>
                                        <p class="text-xs tabular-nums text-base-content/60">
                                            @if ($result->total_steps_count)
                                                {{ number_format($result->total_steps_count) }} steps
                                            @else
                                                Steps pending
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>

        <input
            type="radio"
            name="geoguessr_tabs"
            class="tab grow"
            aria-label="Challenges"
            data-tab="challenges"
            @checked($activeTab === 'challenges')
        >
        <div class="tab-content mt-4 space-y-4" data-geoguessr-challenges>
            @if ($dailies === [])
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title">Challenges</h2>
                        <p class="text-base-content/70">Round locations will show up here after the next GeoGuessr sync.</p>
                    </div>
                </div>
            @else
                <section class="card bg-base-100 shadow-xl">
                    <div class="card-body gap-3 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Daily</p>
                        <div class="flex gap-2 overflow-x-auto pb-1" data-challenge-list>
                            @foreach ($dailies as $daily)
                                <button
                                    type="button"
                                    class="btn btn-ghost btn-sm shrink-0 {{ ! empty($daily['locked']) ? 'opacity-50' : '' }}"
                                    data-challenge-token="{{ $daily['token'] }}"
                                    data-locked="{{ ! empty($daily['locked']) ? 'true' : 'false' }}"
                                >
                                    {{ $daily['label'] }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="card bg-base-100 shadow-xl">
                    <div class="card-body p-4">
                        <h2 class="card-title text-base" data-challenge-title>Challenge</h2>
                        <p class="text-sm text-base-content/70" data-challenge-copy>Actual locations in red. Guesses use each player’s colour.</p>
                        <div class="relative mt-2 h-80 overflow-hidden rounded-xl" data-challenge-map-wrap>
                            <div class="h-full w-full" data-challenge-map></div>
                            <button type="button" class="btn btn-neutral btn-sm absolute right-3 top-3 z-[1100]" data-challenge-fullscreen>Full screen</button>
                        </div>
                    </div>
                </section>

                <section class="card bg-base-100 shadow-xl">
                    <div class="card-body p-4">
                        <h2 class="card-title text-base">Rounds</h2>
                        <div class="mt-2 overflow-x-auto" data-challenge-rounds></div>
                    </div>
                </section>
            @endif
            <div class="toast toast-top toast-end z-[2000]" data-challenge-toast></div>
        </div>

        <input
            type="radio"
            name="geoguessr_tabs"
            class="tab grow"
            aria-label="Graphs"
            data-tab="graphs"
            @checked($activeTab === 'graphs')
        >
        <div class="tab-content mt-4 space-y-4">
            <section class="card bg-base-100 shadow-xl">
                <div class="card-body gap-4 p-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Range</p>
                        <div class="mt-2 flex gap-2 overflow-x-auto pb-1" data-filter-group="range">
                            <button type="button" class="btn btn-ghost btn-sm shrink-0" data-filter="7">7 days</button>
                            <button type="button" class="btn btn-ghost btn-sm shrink-0" data-filter="30">30 days</button>
                            <button type="button" class="btn btn-ghost btn-sm shrink-0" data-filter="all">All</button>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Player</p>
                        <div class="mt-2 flex gap-2 overflow-x-auto pb-1" data-filter-group="player">
                            <button type="button" class="btn btn-ghost btn-sm shrink-0" data-filter="all">Everyone</button>
                            @foreach ($board['players'] as $player)
                                <button type="button" class="btn btn-ghost btn-sm shrink-0" data-filter="{{ $player['id'] }}">
                                    <span class="inline-block h-2.5 w-2.5 rounded-full" style="background: {{ $player['color'] }}"></span>
                                    {{ $player['label'] }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Metric</p>
                        <div class="mt-2 flex gap-2 overflow-x-auto pb-1" data-filter-group="metric">
                            <button type="button" class="btn btn-ghost btn-sm shrink-0" data-filter="score">Score</button>
                            <button type="button" class="btn btn-ghost btn-sm shrink-0" data-filter="xp">XP</button>
                            <button type="button" class="btn btn-ghost btn-sm shrink-0" data-filter="distance">Distance</button>
                            <button type="button" class="btn btn-ghost btn-sm shrink-0" data-filter="steps">Steps</button>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-2 gap-3" data-geoguessr-stats></div>

            <section class="card bg-base-100 shadow-xl">
                <div class="card-body p-4">
                    <h2 class="card-title text-base">Trend</h2>
                    <p class="text-sm text-base-content/70">Daily challenge over time.</p>
                    <div class="relative mt-2 h-56">
                        <canvas data-chart="trend"></canvas>
                    </div>
                    <p class="hidden text-sm text-base-content/60" data-empty="trend">No results in this range yet.</p>
                </div>
            </section>

            <section class="card bg-base-100 shadow-xl">
                <div class="card-body p-4">
                    <h2 class="card-title text-base" data-compare-title>Compare</h2>
                    <p class="text-sm text-base-content/70" data-compare-copy>Average for each player.</p>
                    <div class="relative mt-2 h-56">
                        <canvas data-chart="compare"></canvas>
                    </div>
                    <p class="hidden text-sm text-base-content/60" data-empty="compare">No results in this range yet.</p>
                </div>
            </section>

            <section class="card bg-base-100 shadow-xl">
                <div class="card-body p-4">
                    <h2 class="card-title text-base">Score calendar</h2>
                    <p class="text-sm text-base-content/70">Daily score heat. Darker red is closer to 25,000.</p>
                    <div class="mt-3 overflow-x-auto" data-insight="calendar"></div>
                    <p class="hidden text-sm text-base-content/60" data-empty="calendar">No results in this range yet.</p>
                </div>
            </section>

            <section class="card bg-base-100 shadow-xl">
                <div class="card-body p-4">
                    <h2 class="card-title text-base">Round 1-5</h2>
                    <p class="text-sm text-base-content/70">Average score on each round of the daily.</p>
                    <div class="relative mt-2 h-56">
                        <canvas data-chart="rounds"></canvas>
                    </div>
                    <p class="hidden text-sm text-base-content/60" data-empty="rounds">No round scores in this range yet.</p>
                </div>
            </section>

            <section class="card bg-base-100 shadow-xl">
                <div class="card-body p-4">
                    <h2 class="card-title text-base">Head-to-head</h2>
                    <p class="text-sm text-base-content/70">Same daily, same round. A win is the higher score.</p>
                    <div class="mt-3 overflow-x-auto" data-insight="head-to-head"></div>
                    <p class="hidden text-sm text-base-content/60" data-empty="head-to-head">Need two people on the same daily.</p>
                </div>
            </section>

            <section class="card bg-base-100 shadow-xl">
                <div class="card-body p-4">
                    <h2 class="card-title text-base">Country heat</h2>
                    <p class="text-sm text-base-content/70">Where the daily lands. Size is how often, colour is average score.</p>
                    <div class="relative mt-2 h-64 overflow-hidden rounded-xl" data-insight-map-wrap="countries">
                        <div class="h-full w-full" data-insight-map="countries"></div>
                        <button type="button" class="btn btn-neutral btn-sm absolute right-3 top-3 z-[1100]" data-map-fullscreen>Full screen</button>
                    </div>
                    <p class="hidden text-sm text-base-content/60" data-empty="countries">No country data in this range yet.</p>
                </div>
            </section>

            <section class="card bg-base-100 shadow-xl">
                <div class="card-body p-4">
                    <h2 class="card-title text-base">Guess heat</h2>
                    <p class="text-sm text-base-content/70">Where people click. Brighter spots are denser guesses.</p>
                    <div class="relative mt-2 h-64 overflow-hidden rounded-xl" data-insight-map-wrap="guesses">
                        <div class="h-full w-full" data-insight-map="guesses"></div>
                        <button type="button" class="btn btn-neutral btn-sm absolute right-3 top-3 z-[1100]" data-map-fullscreen>Full screen</button>
                    </div>
                    <p class="hidden text-sm text-base-content/60" data-empty="guesses">No guesses in this range yet.</p>
                </div>
            </section>

            <section class="card bg-base-100 shadow-xl">
                <div class="card-body p-4">
                    <h2 class="card-title text-base">Continent leaderboard</h2>
                    <p class="text-sm text-base-content/70">Best average score on each continent.</p>
                    <div class="mt-3 overflow-x-auto" data-insight="continents"></div>
                    <p class="hidden text-sm text-base-content/60" data-empty="continent-board">No continent data in this range yet.</p>
                </div>
            </section>

            <section class="card bg-base-100 shadow-xl">
                <div class="card-body p-4">
                    <h2 class="card-title text-base">Country leaderboard</h2>
                    <p class="text-sm text-base-content/70">Best average score in each country.</p>
                    <div class="mt-3 overflow-x-auto" data-insight="countries"></div>
                    <p class="hidden text-sm text-base-content/60" data-empty="country-board">No country data in this range yet.</p>
                </div>
            </section>
        </div>
        <div class="toast toast-top toast-end z-[2000]" data-reward-toast></div>
    </div>

    <script type="application/json" data-geoguessr-data>@json($board)</script>
    <script type="application/json" data-geoguessr-insights>@json($insights)</script>
    <script type="application/json" data-geoguessr-dailies>@json($dailies)</script>
@endsection
