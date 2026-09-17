@extends('layouts.app')

@section('title', 'Spirdle — '.config('app.name'))

@section('content')
    <div class="mb-4">
        <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm -ml-2">← Back</a>
    </div>

    <div class="mb-5">
        <h1 class="text-xl font-bold sm:text-3xl">Spirdle</h1>
        <p class="mt-1 text-sm text-base-content/70 sm:text-base">Five letters. Six tries. Same word for the club.</p>
    </div>

    @if (session('status'))
        <div role="alert" class="alert alert-success mb-5">
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div
        class="tabs tabs-box tabs-xs w-full sm:tabs-md lg:tabs-lg"
        data-spirdle-board
        data-live-poll
        data-poll-url="{{ route('spirdle.live', absolute: false) }}"
        data-poll-tab="today"
        data-revision="{{ $revision }}"
    >
        <input
            type="radio"
            name="spirdle_tabs"
            class="tab grow"
            aria-label="Today"
            data-tab="today"
            @checked($activeTab === 'today')
        >
        <div class="tab-content mt-4 space-y-4">
            <div data-live-region="today">
                @include('spirdle.today')
            </div>
        </div>

        <input
            type="radio"
            name="spirdle_tabs"
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
                                href="{{ route('spirdle.index', ['tab' => 'weekly', 'week' => $weekly['previousStart']]) }}"
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
                                href="{{ route('spirdle.index', ['tab' => 'weekly', 'week' => $weekly['nextStart']]) }}"
                                class="btn btn-ghost btn-sm btn-square shrink-0"
                                aria-label="Next week"
                            >→</a>
                        @else
                            <button type="button" class="btn btn-ghost btn-sm btn-square shrink-0" disabled aria-label="Next week">→</button>
                        @endif
                    </div>
                    <p class="text-center text-xs text-base-content/55">{{ $weekly['range'] }} · {{ $weekly['daysLogged'] }}/7 days with a Spirdle</p>
                </div>
            </section>

            @if ($weekly['standings'] === [])
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title">Standings</h2>
                        <p class="text-base-content/70">Nobody has finished a Spirdle this week yet.</p>
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
                                            {{ $row['wins'] }}/{{ $row['played'] }} won
                                            @if ($row['average'] !== null)
                                                <span class="text-base-content/30">·</span>
                                                avg {{ $row['average'] }} guesses
                                            @endif
                                            @if ($row['fastest'])
                                                <span class="text-base-content/30">·</span>
                                                fastest {{ $row['fastest'] }}
                                            @endif
                                        </p>
                                    </div>
                                    <p class="shrink-0 text-right">
                                        <span class="text-2xl font-bold tabular-nums leading-none">{{ $row['total'] }}</span>
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
                                <p class="text-sm text-base-content/55">No finishes.</p>
                            @else
                                <ol class="space-y-1.5">
                                    @foreach ($day['results'] as $result)
                                        <li class="flex items-center gap-2 text-sm">
                                            <span class="inline-block h-2 w-2 shrink-0 rounded-full" style="background: {{ $result['color'] }}"></span>
                                            <span class="min-w-0 flex-1 truncate">{{ $result['label'] }}</span>
                                            <span class="tabular-nums font-medium">
                                                @if ($result['won'])
                                                    {{ $result['guesses'] }}
                                                @else
                                                    X
                                                @endif
                                            </span>
                                            @if ($result['duration'])
                                                <span class="tabular-nums text-xs text-base-content/55">{{ $result['duration'] }}</span>
                                            @endif
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
            name="spirdle_tabs"
            class="tab grow"
            aria-label="Challenges"
            data-tab="challenges"
            @checked($activeTab === 'challenges')
        >
        <div class="tab-content mt-4 space-y-4" data-spirdle-challenges>
            @include('spirdle.challenges')
        </div>

        <input
            type="radio"
            name="spirdle_tabs"
            class="tab grow"
            aria-label="You"
            data-tab="you"
            @checked($activeTab === 'you')
        >
        <div class="tab-content mt-4 space-y-4">
            @include('spirdle.you')
        </div>
        <div class="toast toast-top toast-end z-[2000]" data-reward-toast></div>
    </div>

    <script type="application/json" data-spirdle-dailies>@json(collect($dailies)->map(fn ($daily) => \Illuminate\Support\Arr::except($daily, ['board']))->values())</script>
@endsection
