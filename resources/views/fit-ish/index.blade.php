@extends('layouts.app')

@section('title', 'Fit-Ish — '.config('app.name'))

@section('content')
    <div class="mb-4">
        <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm -ml-2">← Back</a>
    </div>

    <div class="mb-5">
        <h1 class="text-xl font-bold sm:text-3xl">Fit-Ish</h1>
        <p class="mt-1 text-sm text-base-content/70 sm:text-base">Lionheart classes, points, and who actually showed up.</p>
    </div>

    @if (session('status'))
        <div role="alert" class="alert alert-success mb-5">
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div
        class="tabs tabs-box tabs-xs w-full sm:tabs-md lg:tabs-lg"
        data-fit-ish-board
        data-session-day-url="{{ url('/fit-ish/days') }}/__DATE__"
    >
        <input
            type="radio"
            name="fit_ish_tabs"
            class="tab grow"
            aria-label="Today"
            data-tab="today"
            @checked($activeTab === 'today')
        >
        <div class="tab-content mt-4 space-y-4">
            @include('fit-ish.today')
        </div>

        <input
            type="radio"
            name="fit_ish_tabs"
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
                                href="{{ route('fit-ish.index', ['tab' => 'weekly', 'week' => $weekly['previousStart']]) }}"
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
                                href="{{ route('fit-ish.index', ['tab' => 'weekly', 'week' => $weekly['nextStart']]) }}"
                                class="btn btn-ghost btn-sm btn-square shrink-0"
                                aria-label="Next week"
                            >→</a>
                        @else
                            <button type="button" class="btn btn-ghost btn-sm btn-square shrink-0" disabled aria-label="Next week">→</button>
                        @endif
                    </div>
                    <p class="text-center text-xs text-base-content/55">{{ $weekly['range'] }} · {{ $weekly['daysLogged'] }}/7 days with a class</p>
                </div>
            </section>

            @if ($weekly['standings'] === [])
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title">Standings</h2>
                        <p class="text-base-content/70">Nobody has a Lionheart class this week yet.</p>
                    </div>
                </div>
            @else
                <h2 class="text-lg font-bold">Standings</h2>
                <ol class="space-y-2.5 sm:space-y-3">
                    @foreach ($weekly['standings'] as $row)
                        <li class="card bg-base-100 shadow-md">
                            <div class="card-body p-3.5 sm:p-4">
                                <div class="flex items-start gap-3">
                                    <span
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
                                            {{ $row['played'] }} {{ \Illuminate\Support\Str::plural('class', $row['played']) }}
                                            <span class="text-base-content/30">·</span>
                                            avg {{ number_format($row['average'], 1) }}
                                            @if ($row['best'] !== null)
                                                <span class="text-base-content/30">·</span>
                                                best {{ number_format($row['best'], 1) }}
                                                @if ($row['bestDate'])
                                                    ({{ $row['bestDate'] }})
                                                @endif
                                            @endif
                                        </p>
                                    </div>
                                    <p class="shrink-0 text-right">
                                        <span class="text-2xl font-bold tabular-nums leading-none">{{ number_format($row['total'], 1) }}</span>
                                        <span class="mt-1 block text-xs text-base-content/50">pts</span>
                                    </p>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif

            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($weekly['days'] as $day)
                    <section @class(['card bg-base-100 shadow-md', 'ring-2 ring-primary' => $day['isToday']])>
                        <div class="card-body gap-2 p-3.5">
                            <div class="flex items-baseline justify-between gap-2">
                                <h3 class="font-semibold">{{ $day['name'] }}</h3>
                                <p class="text-xs text-base-content/55">{{ $day['short'] }}</p>
                            </div>
                            @if ($day['results'] === [])
                                <p class="text-sm text-base-content/55">No class logged.</p>
                            @else
                                <ol class="space-y-1.5">
                                    @foreach ($day['results'] as $result)
                                        <li class="flex items-center gap-2 text-sm">
                                            <span class="inline-block h-2 w-2 shrink-0 rounded-full" style="background: {{ $result['color'] }}"></span>
                                            <span class="min-w-0 flex-1 truncate">{{ $result['label'] }}</span>
                                            <span class="tabular-nums font-medium">{{ number_format((float) $result['score'], 1) }}</span>
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        </div>

        <input
            type="radio"
            name="fit_ish_tabs"
            class="tab grow"
            aria-label="Sessions"
            data-tab="sessions"
            @checked($activeTab === 'sessions')
        >
        <div class="tab-content mt-4 space-y-4">
            @include('fit-ish.sessions')
        </div>

        <input
            type="radio"
            name="fit_ish_tabs"
            class="tab grow"
            aria-label="Workouts"
            data-tab="workouts"
            @checked($activeTab === 'workouts')
        >
        <div class="tab-content mt-4 space-y-4">
            @include('fit-ish.workouts')
        </div>

        <input
            type="radio"
            name="fit_ish_tabs"
            class="tab grow"
            aria-label="You"
            data-tab="you"
            @checked($activeTab === 'you')
        >
        <div class="tab-content mt-4 space-y-4">
            @include('fit-ish.you')

            @if ($summaries->isEmpty())
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title">Your Lionheart</h2>
                        <p class="text-base-content/70">Sync from your profile to pull all-time averages and max scores.</p>
                    </div>
                </div>
            @else
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach (['allTime', 'year', 'month', 'week', 'last7Days', 'last30Days'] as $key)
                        @php $row = $summaries->get($key); @endphp
                        @if ($row)
                            <section class="card bg-base-100 shadow-md">
                                <div class="card-body gap-2 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">{{ $row->timeframe_name }}</p>
                                    <p class="text-3xl font-bold tabular-nums leading-none">{{ number_format((float) $row->average_points, 1) }}</p>
                                    <p class="text-sm text-base-content/60">avg points · max {{ number_format((float) $row->max_points, 1) }}</p>
                                    <p class="text-xs text-base-content/55">
                                        {{ $row->session_count }} {{ \Illuminate\Support\Str::plural('class', $row->session_count) }}
                                        · avg {{ number_format((int) $row->average_calories) }} kcal
                                    </p>
                                </div>
                            </section>
                        @endif
                    @endforeach
                </div>
            @endif

            <section class="space-y-3">
                <h2 class="text-lg font-bold">Recent classes</h2>
                @if ($recent->isEmpty())
                    <div class="card bg-base-100 shadow-xl">
                        <div class="card-body">
                            <p class="text-base-content/70">No classes synced yet. Press Sync on your Fit-Ish profile tab.</p>
                        </div>
                    </div>
                @else
                    <ol class="space-y-2.5 sm:space-y-3">
                        @foreach ($recent as $session)
                            @include('fit-ish.session-card', ['session' => $session, 'place' => null, 'showName' => false, 'showHeartrate' => false])
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>
    </div>
@endsection
