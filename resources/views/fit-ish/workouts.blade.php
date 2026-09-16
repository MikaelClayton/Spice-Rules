@if ($workouts === [])
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title">Workouts</h2>
            <p class="text-base-content/70">Workout logos and names show up here after a Lionheart class is synced.</p>
        </div>
    </div>
@else
    <h2 class="text-lg font-bold">Workouts</h2>
    <ul class="grid gap-3 sm:grid-cols-2">
        @foreach ($workouts as $workout)
            <li class="card bg-base-100 shadow-md">
                <div class="card-body gap-3 p-3.5 sm:p-4">
                    <div class="flex items-start gap-3">
                        @if ($workout['logoUrl'])
                            @include('fit-ish.workout-logo', [
                                'src' => $workout['logoUrl'],
                                'box' => 'h-14 w-14 rounded-xl',
                            ])
                        @else
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-base-200 text-lg font-bold text-base-content/40">
                                {{ \Illuminate\Support\Str::substr($workout['displayName'], 0, 1) }}
                            </div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold leading-tight">{{ $workout['displayName'] }}</p>
                            @if ($workout['type'])
                                <p class="mt-0.5 text-xs capitalize text-base-content/60">{{ $workout['type'] }}</p>
                            @endif
                        </div>
                    </div>
                    @if ($workout['description'])
                        <p class="text-sm leading-relaxed text-base-content/70">{{ $workout['description'] }}</p>
                    @endif
                    <dl class="grid grid-cols-3 gap-2 text-center text-xs sm:text-sm">
                        <div class="rounded-lg bg-base-200 px-2 py-2">
                            <dt class="text-base-content/50">Classes</dt>
                            <dd class="mt-0.5 font-semibold tabular-nums">{{ $workout['sessions'] }}</dd>
                        </div>
                        <div class="rounded-lg bg-base-200 px-2 py-2">
                            <dt class="text-base-content/50">Best</dt>
                            <dd class="mt-0.5 font-semibold tabular-nums">
                                {{ $workout['best'] === null ? '—' : number_format($workout['best'], 1) }}
                            </dd>
                        </div>
                        <div class="rounded-lg bg-base-200 px-2 py-2">
                            <dt class="text-base-content/50">Avg</dt>
                            <dd class="mt-0.5 font-semibold tabular-nums">
                                {{ $workout['average'] === null ? '—' : number_format($workout['average'], 1) }}
                            </dd>
                        </div>
                    </dl>
                    <p class="text-xs text-base-content/55">
                        {{ $workout['people'] }} {{ \Illuminate\Support\Str::plural('person', $workout['people']) }}
                        @if ($workout['lastDate'])
                            <span class="text-base-content/30">·</span>
                            last {{ $workout['lastDate'] }}
                        @endif
                    </p>
                </div>
            </li>
        @endforeach
    </ul>
@endif
