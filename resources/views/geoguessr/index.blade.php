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
        <div
            class="tab-content mt-4 space-y-4"
            data-live-poll
            data-poll-url="{{ route('geoguessr.live') }}"
            data-revision="{{ $revision }}"
        >
            <div data-live-region="today">
                @include('geoguessr.today')
            </div>
        </div>

        <input
            type="radio"
            name="geoguessr_tabs"
            class="tab grow"
            aria-label="Weekly"
            data-tab="weekly"
            @checked($activeTab === 'weekly')
        >
        <div class="tab-content mt-4 space-y-4">
            <section class="card bg-base-100 shadow-xl">
                <div class="card-body gap-3 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Week</p>
                    <div class="flex items-center gap-2">
                        @if ($weekly['hasPrevious'])
                            <a
                                href="{{ route('geoguessr.index', ['tab' => 'weekly', 'week' => $weekly['previousStart']]) }}"
                                class="btn btn-ghost btn-sm btn-square shrink-0"
                                aria-label="Previous week"
                            >←</a>
                        @else
                            <button type="button" class="btn btn-ghost btn-sm btn-square shrink-0" disabled aria-label="Previous week">←</button>
                        @endif
                        <div class="min-w-0 flex-1 text-center">
                            <h2 class="text-lg font-bold leading-tight">{{ $weekly['label'] }}</h2>
                            <p class="mt-0.5 text-sm text-base-content/70">Sunday to Sunday</p>
                            @if ($weekly['isCurrent'])
                                <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-primary">This week</p>
                            @endif
                        </div>
                        @if ($weekly['hasNext'])
                            <a
                                href="{{ route('geoguessr.index', ['tab' => 'weekly', 'week' => $weekly['nextStart']]) }}"
                                class="btn btn-ghost btn-sm btn-square shrink-0"
                                aria-label="Next week"
                            >→</a>
                        @else
                            <button type="button" class="btn btn-ghost btn-sm btn-square shrink-0" disabled aria-label="Next week">→</button>
                        @endif
                    </div>
                    <p class="text-center text-xs text-base-content/55">{{ $weekly['range'] }} · {{ $weekly['daysLogged'] }}/7 days logged</p>
                </div>
            </section>

            @if ($weekly['standings'] === [])
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title">Standings</h2>
                        <p class="text-base-content/70">Nobody has logged a daily this week yet.</p>
                    </div>
                </div>
            @else
                <ol class="space-y-2.5 sm:space-y-3">
                    @foreach ($weekly['standings'] as $row)
                        <li class="card bg-base-100 shadow-md">
                            <div class="card-body p-3.5 sm:p-4">
                                <div class="flex items-start gap-3">
                                    <span
                                        data-weekly-rank="{{ $row['place'] }}"
                                        @class([
                                            'badge badge-md sm:badge-lg mt-0.5 shrink-0 tabular-nums',
                                            'badge-warning' => $row['place'] === 1,
                                            'badge-ghost' => $row['place'] === 2,
                                            'badge-accent' => $row['place'] === 3,
                                            'badge-neutral' => $row['place'] > 3,
                                        ])
                                    >{{ $row['place'] }}</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="flex min-w-0 items-center gap-2 font-semibold leading-tight">
                                            <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $row['color'] }}"></span>
                                            <span class="truncate">{{ $row['label'] }}</span>
                                        </p>
                                        <p class="mt-1 text-xs text-base-content/60">
                                            {{ $row['played'] }}/7 days
                                            <span class="text-base-content/30">·</span>
                                            avg {{ number_format($row['average']) }}
                                            @if ($row['best'] !== null)
                                                <span class="text-base-content/30">·</span>
                                                best {{ number_format($row['best']) }}
                                                @if ($row['bestDate'])
                                                    ({{ $row['bestDate'] }})
                                                @endif
                                            @endif
                                        </p>
                                    </div>
                                    <p class="shrink-0 text-lg font-bold leading-none tabular-nums sm:text-xl">{{ number_format($row['total']) }}</p>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif

            <section class="card bg-base-100 shadow-xl">
                <div class="card-body gap-3 p-4">
                    <h2 class="card-title text-base">The week</h2>
                    <p class="text-sm text-base-content/70">Each daily from this Sunday through Saturday. Next Sunday starts the new week.</p>
                    <ol class="space-y-2">
                        @foreach ($weekly['days'] as $day)
                            <li @class([
                                'rounded-xl bg-base-200 p-3',
                                'ring-2 ring-primary' => $day['isToday'],
                            ])>
                                <div class="flex items-baseline justify-between gap-2">
                                    <p class="font-semibold leading-tight">{{ $day['name'] }}</p>
                                    <p class="text-xs text-base-content/55">{{ $day['short'] }}</p>
                                </div>
                                @if ($day['results'] === [])
                                    <p class="mt-2 text-sm text-base-content/55">No scores</p>
                                @else
                                    <ul class="mt-2 space-y-1.5">
                                        @foreach ($day['results'] as $index => $result)
                                            <li class="flex items-center gap-2 text-sm">
                                                <span class="inline-block h-2 w-2 shrink-0 rounded-full" style="background: {{ $result['color'] }}"></span>
                                                <span class="min-w-0 flex-1 truncate {{ $index === 0 ? 'font-semibold' : '' }}">{{ $result['label'] }}</span>
                                                @if ($result['team'])
                                                    <span aria-label="Played as a team">🤝</span>
                                                @endif
                                                <span class="shrink-0 tabular-nums {{ $index === 0 ? 'font-semibold' : 'text-base-content/70' }}">
                                                    {{ $result['score'] === null ? '—' : number_format($result['score']) }}
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>
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
                        <h2 class="card-title text-base">The day</h2>
                        <div class="mt-2" data-challenge-summary></div>
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
