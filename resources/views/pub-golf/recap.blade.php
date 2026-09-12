@extends('layouts.app')

@section('title', $crawl->name.' recap — Pub Golf — '.config('app.name'))

@section('content')
    <div class="pb-24">
    <div class="mb-4">
        <a href="{{ route('pub-golf.index') }}" class="btn btn-ghost btn-sm -ml-2">← Back</a>
    </div>

    <div class="mb-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">You called it</p>
        <h1 class="text-2xl font-bold sm:text-3xl">{{ $crawl->name }}</h1>
        <p class="mt-1 text-base-content/70">
            {{ $recap['joined_at'] }} → {{ $recap['left_at'] }}
            @if ($recap['group_still_going'])
                · the rest of the crew is still out
            @endif
        </p>
    </div>

    @if (session('status'))
        <div role="alert" class="alert alert-success mb-5">
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div role="alert" class="alert alert-error mb-5">
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    @if ($recap['group_still_going'])
        <form method="POST" action="{{ route('pub-golf.rejoins.store', $crawl) }}" class="mb-5">
            @csrf
            <button type="submit" class="btn btn-primary">Rejoin the crawl</button>
        </form>
    @endif

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-5">
        <div class="card bg-base-100 shadow-md">
            <div class="card-body gap-1 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Out for</p>
                <p class="text-3xl font-bold tabular-nums leading-none">{{ $recap['pace']['duration_label'] }}</p>
            </div>
        </div>
        <div class="card bg-base-100 shadow-md">
            <div class="card-body gap-1 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Drinks</p>
                <p class="text-3xl font-bold tabular-nums leading-none">{{ $recap['alcoholic_count'] }}</p>
            </div>
        </div>
        <div class="card bg-base-100 shadow-md">
            <div class="card-body gap-1 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Per hour</p>
                <p class="text-3xl font-bold tabular-nums leading-none">{{ $recap['pace']['drinks_per_hour'] }}</p>
            </div>
        </div>
        <div class="card bg-base-100 shadow-md">
            <div class="card-body gap-1 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Place</p>
                <p class="text-3xl font-bold tabular-nums leading-none">{{ $recap['rank'] }}<span class="text-lg font-medium text-base-content/50">/{{ $recap['field_size'] }}</span></p>
            </div>
        </div>
    </div>

    <div class="card mb-5 bg-base-100 shadow-md">
        <div class="card-body gap-2 p-4">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Pace</p>
                <p class="text-sm text-base-content/70">{{ $recap['units'] }} standard drinks</p>
            </div>
            @include('pub-golf.pace', ['pace' => $recap['pace']])
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2 mb-5" data-pub-golf-recap>
        <section class="card bg-base-100 shadow-xl">
            <div class="card-body p-4 sm:p-5">
                <h2 class="card-title">Drinks per hour</h2>
                <div class="relative mt-2 h-56">
                    <canvas data-chart="hourly"></canvas>
                </div>
            </div>
        </section>
        <section class="card bg-base-100 shadow-xl">
            <div class="card-body p-4 sm:p-5">
                <h2 class="card-title">What you drank</h2>
                <div class="relative mt-2 h-56">
                    <canvas data-chart="categories"></canvas>
                </div>
            </div>
        </section>
        <section class="card bg-base-100 shadow-xl lg:col-span-2">
            <div class="card-body p-4 sm:p-5">
                <h2 class="card-title">Running total</h2>
                <div class="relative mt-2 h-56">
                    <canvas data-chart="cumulative"></canvas>
                </div>
            </div>
        </section>
        <script type="application/json" data-pub-golf-recap-charts>@json($recap['charts'])</script>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="card bg-base-100 shadow-xl">
            <div class="card-body p-4 sm:p-5">
                <h2 class="card-title">Your round</h2>
                @if ($recap['by_drink'] === [])
                    <p class="text-sm text-base-content/70">You called it without logging a drink.</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($recap['by_drink'] as $row)
                            <li class="flex items-center justify-between gap-3">
                                <span>{{ $row['emoji'] }} {{ $row['label'] }}</span>
                                <span class="font-bold tabular-nums">{{ $row['count'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($recap['timeline'] !== [])
                        <h3 class="mt-4 text-sm font-semibold text-base-content/70">Timeline</h3>
                        <ol class="mt-2 space-y-1.5 text-sm">
                            @foreach ($recap['timeline'] as $item)
                                <li class="flex justify-between gap-3">
                                    <span>{{ $item['emoji'] }} {{ $item['label'] }}</span>
                                    <span class="tabular-nums text-base-content/50">{{ $item['time'] }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                @endif
            </div>
        </section>

        <section class="card bg-base-100 shadow-xl">
            <div class="card-body p-4 sm:p-5">
                <h2 class="card-title">The field</h2>
                <ol class="space-y-2">
                    @foreach ($recap['standings'] as $index => $row)
                        <li class="flex items-center justify-between gap-3 rounded-xl px-2 py-1.5 {{ $row['is_you'] ? 'bg-secondary/20' : '' }}">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="w-5 text-sm font-bold tabular-nums text-base-content/50">{{ $index + 1 }}</span>
                                <span class="h-3 w-3 shrink-0 rounded-full" style="background: {{ $row['color'] }}"></span>
                                <span class="truncate font-medium">
                                    {{ $row['name'] }}
                                    @if ($row['is_you'])
                                        <span class="text-base-content/50">(you)</span>
                                    @endif
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($row['still_in'])
                                    <span class="badge badge-secondary badge-sm">Still out</span>
                                @endif
                                <span class="font-bold tabular-nums">{{ $row['alcoholic'] }}</span>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    </div>

    <p class="mt-6 text-center text-sm text-base-content/60">Drink water, get home safe.</p>
    </div>

    @include('pub-golf.chat')
@endsection
