@extends('layouts.app')

@section('title', 'GeoGuessr — '.config('app.name'))

@section('content')
    <div class="mb-4">
        <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm -ml-2">← Back</a>
    </div>

    <div class="mb-5">
        <h1 class="text-3xl font-bold">GeoGuessr</h1>
        <p class="mt-1 text-base-content/70">How everyone did on {{ $date->toFormattedDateString() }}.</p>
    </div>

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

    <div class="tabs tabs-box tabs-lg w-full" data-geoguessr-board>
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
                <ol class="space-y-3">
                    @foreach ($results as $index => $result)
                        @php
                            $isYou = $result->geoguesser?->user_id === Auth::id();
                            $name = $result->geoguesser?->user?->name ?? $result->geoguesser?->username;
                            $nick = $result->geoguesser?->username;
                        @endphp
                        <li class="card bg-base-100 shadow-xl {{ $isYou ? 'ring-2 ring-primary' : '' }}">
                            <div class="card-body flex-row items-center gap-3 p-4">
                                <span @class([
                                    'badge badge-lg tabular-nums',
                                    'badge-warning' => $index === 0,
                                    'badge-ghost' => $index === 1,
                                    'badge-accent' => $index === 2,
                                    'badge-neutral' => $index > 2,
                                ])>{{ $index + 1 }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate font-semibold">{{ $name }}</p>
                                        @if ($isYou)
                                            <span class="badge badge-primary badge-sm">You</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-xs text-base-content/60">
                                        @if ($nick && $nick !== $name)
                                            {{ $nick }} ·
                                        @endif
                                        @if ($result->total_distance)
                                            {{ number_format($result->total_distance / 1000, 1) }} km
                                        @else
                                            Distance pending
                                        @endif
                                        ·
                                        @if ($result->total_steps_count)
                                            {{ number_format($result->total_steps_count) }} steps
                                        @else
                                            Steps pending
                                        @endif
                                    </p>
                                </div>
                                <p class="text-right text-xl font-bold tabular-nums">{{ number_format($result->total_score) }}</p>
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
        </div>
    </div>

    <script type="application/json" data-geoguessr-data>@json($board)</script>
@endsection
