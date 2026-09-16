@php
    $you = $you ?? ['hasData' => false];
@endphp

@if ($you['hasData'])
    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
        <section class="card min-w-0 bg-base-100 shadow-md">
            <div class="card-body gap-2 p-3.5 sm:p-4">
                <h2 class="text-sm font-semibold leading-tight">Heart rate</h2>
                <p class="text-xs text-base-content/55">Min–max bpm across your last {{ count($you['heartrate']['columns']) }} classes</p>
                @if ($you['heartrate']['columns'] === [])
                    <p class="text-sm text-base-content/55">No heart-rate range yet.</p>
                @else
                    <div class="grid grid-cols-[2rem_minmax(0,1fr)] gap-x-1.5">
                        <div class="flex h-36 flex-col justify-between py-0.5 text-right text-[10px] leading-none text-base-content/55 tabular-nums sm:h-40">
                            <span>{{ $you['heartrate']['ceiling'] }}</span>
                            <span>bpm</span>
                            <span>{{ $you['heartrate']['floor'] }}</span>
                        </div>
                        <div class="relative h-36 min-w-0 overflow-x-auto sm:h-40">
                            <div class="absolute inset-y-0 left-0 flex min-w-full items-stretch gap-px">
                                @foreach ($you['heartrate']['columns'] as $column)
                                    <div
                                        class="relative min-w-[5px] flex-1"
                                        title="{{ $column['min'] }}–{{ $column['max'] }} bpm{{ $column['average'] ? ' · avg '.$column['average'] : '' }}"
                                    >
                                        <div
                                            class="absolute inset-x-0 rounded-sm bg-info"
                                            style="bottom: {{ $column['bottom'] }}%; height: {{ $column['height'] }}%"
                                        ></div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div></div>
                        <div class="flex justify-between text-[10px] tabular-nums text-base-content/55">
                            <span>1</span>
                            <span>{{ count($you['heartrate']['columns']) }}</span>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        <section class="card min-w-0 bg-base-100 shadow-md">
            <div class="card-body gap-2 p-3.5 sm:p-4">
                <h2 class="text-sm font-semibold leading-tight">Classes</h2>
                <p class="text-xs text-base-content/55">Last year · darker is a bigger score</p>
                <div class="overflow-x-auto pb-1">
                    @php
                        $monthLabels = collect($you['heatmap']['months'])->pluck('label', 'index');
                    @endphp
                    <div class="inline-flex min-w-full flex-col gap-1">
                        <div class="flex gap-[3px] pl-5">
                            @foreach ($you['heatmap']['weeks'] as $index => $week)
                                <div class="w-2.5 shrink-0 text-[9px] leading-none text-base-content/50 sm:w-3">
                                    {{ $monthLabels[$index] ?? '' }}
                                </div>
                            @endforeach
                        </div>
                        <div class="flex gap-1">
                            <div class="flex w-4 shrink-0 flex-col justify-between py-0.5 text-[9px] leading-none text-base-content/45">
                                <span></span>
                                <span>M</span>
                                <span></span>
                                <span>W</span>
                                <span></span>
                                <span>F</span>
                                <span></span>
                            </div>
                            <div class="flex gap-[3px]">
                                @foreach ($you['heatmap']['weeks'] as $week)
                                    <div class="flex w-2.5 shrink-0 flex-col gap-[3px] sm:w-3">
                                        @foreach ($week as $cell)
                                            <span
                                                class="block h-2.5 rounded-[2px] sm:h-3 {{ $cell['color'] ? '' : 'bg-base-300' }}"
                                                style="{{ $cell['color'] ? 'background: '.$cell['color'] : '' }}"
                                                title="{{ $cell['title'] }}"
                                            ></span>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="card min-w-0 bg-base-100 shadow-md">
            <div class="card-body justify-center gap-4 p-4 text-center">
                <div>
                    <p class="text-4xl font-bold tabular-nums leading-none text-primary sm:text-5xl">{{ number_format($you['stats']['longestStreak']) }}</p>
                    <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-base-content/50">Longest streak (days)</p>
                </div>
                <div>
                    <p class="text-4xl font-bold tabular-nums leading-none text-primary sm:text-5xl">{{ number_format($you['stats']['totalCalories']) }}</p>
                    <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-base-content/50">Total calories</p>
                </div>
                <div>
                    <p class="text-4xl font-bold tabular-nums leading-none text-primary sm:text-5xl">{{ number_format($you['stats']['maxCalories']) }}</p>
                    <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-base-content/50">Max calories</p>
                </div>
            </div>
        </section>

        @include('fit-ish.chart-canvas', [
            'type' => 'monthly',
            'title' => 'Monthly mix',
            'caption' => 'Classes stacked by workout type',
            'payload' => $you['monthly'],
        ])
    </div>

    <div class="grid min-w-0 grid-cols-1 gap-3 lg:grid-cols-2">
        @include('fit-ish.chart-canvas', [
            'type' => 'radar',
            'title' => 'Zones by workout',
            'caption' => 'Your average time in each heart-rate zone, by type',
            'payload' => $you['typeRadar'],
            'showLegend' => true,
        ])
        @include('fit-ish.chart-canvas', [
            'type' => 'zone-stacks',
            'title' => 'Zone mix',
            'caption' => 'Share of class time in each zone',
            'payload' => $you['zoneStacks'],
        ])
    </div>

    @if ($you['workouts'] !== [])
        <section class="card min-w-0 bg-base-100 shadow-md">
            <div class="card-body gap-2 p-3.5 sm:p-4">
                <h2 class="text-sm font-semibold leading-tight">Workouts</h2>
                <p class="text-xs text-base-content/55">Your averages · warmer means higher</p>
                <div class="overflow-x-auto">
                    <table class="table-sm table min-w-[28rem]">
                        <thead>
                            <tr class="text-xs text-base-content/50">
                                <th class="sticky left-0 bg-base-100 font-medium">Workout</th>
                                <th class="text-right font-medium">Pts</th>
                                <th class="text-right font-medium">Cal</th>
                                <th class="text-right font-medium">HR</th>
                                <th class="text-right font-medium">#</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($you['workouts'] as $workout)
                                <tr>
                                    <th class="sticky left-0 bg-base-100 text-sm font-semibold">
                                        <span class="block max-w-[10rem] truncate sm:max-w-none">{{ $workout['name'] }}</span>
                                    </th>
                                    <td class="p-1">
                                        <span class="flex min-h-8 items-center justify-end rounded-lg px-2 text-sm font-semibold tabular-nums" style="{{ $workout['pointsStyle'] }}">{{ number_format($workout['points'], 1) }}</span>
                                    </td>
                                    <td class="p-1">
                                        <span class="flex min-h-8 items-center justify-end rounded-lg px-2 text-sm font-semibold tabular-nums" style="{{ $workout['caloriesStyle'] }}">{{ number_format($workout['calories']) }}</span>
                                    </td>
                                    <td class="p-1">
                                        <span class="flex min-h-8 items-center justify-end rounded-lg px-2 text-sm font-semibold tabular-nums" style="{{ $workout['heartrateStyle'] }}">{{ number_format($workout['heartrate']) }}</span>
                                    </td>
                                    <td class="text-right text-sm tabular-nums text-base-content/70">{{ $workout['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif
@endif
