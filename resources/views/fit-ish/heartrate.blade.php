@php
    $chart = $session->heartrateChart();
@endphp

@if ($chart !== null || $session->zones->isNotEmpty())
    <section class="space-y-2">
        @if ($chart !== null)
            <div class="flex items-baseline justify-between gap-2">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Heart rate</h3>
                @if ($chart['average'] !== null)
                    <p class="text-[11px] tabular-nums text-base-content/60">avg {{ $chart['average'] }} bpm</p>
                @endif
            </div>
            <p class="text-[11px] text-base-content/55">Each bar is that minute's min–max, colored by zone</p>
            <div class="grid grid-cols-[2.5rem_minmax(0,1fr)] gap-x-2 gap-y-1">
                <div class="flex h-40 flex-col justify-between py-0.5 text-right text-[10px] leading-none text-base-content/55 tabular-nums">
                    <span>{{ $chart['ceiling'] }}</span>
                    <span>bpm</span>
                    <span>{{ $chart['floor'] }}</span>
                </div>
                <div class="relative h-40 min-w-0 overflow-hidden rounded-lg bg-base-200">
                    @foreach ($chart['bands'] as $band)
                        <div
                            class="pointer-events-none absolute inset-x-0"
                            style="bottom: {{ $band['bottom'] }}%; height: {{ $band['height'] }}%; background: {{ $band['fill'] }}"
                            title="{{ $band['name'] }}"
                        ></div>
                    @endforeach
                    @if ($chart['averagePct'] !== null)
                        <div
                            class="pointer-events-none absolute inset-x-0 z-10 border-t border-dashed border-base-content/40"
                            style="bottom: {{ $chart['averagePct'] }}%"
                        ></div>
                    @endif
                    <div class="absolute inset-0 z-20 flex items-stretch gap-px px-0.5">
                        @foreach ($chart['columns'] as $column)
                            <div class="relative min-w-0 flex-1" title="{{ $column['label'] }}">
                                <div
                                    class="absolute inset-x-0 rounded-sm {{ $column['recorded'] ? '' : 'opacity-40' }}"
                                    style="bottom: {{ $column['bottom'] }}%; height: {{ $column['height'] }}%; background: {{ $column['color'] }}"
                                ></div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div></div>
                <div class="flex justify-between text-[10px] tabular-nums text-base-content/55">
                    <span>0 min</span>
                    <span>{{ $chart['endMinute'] }} min</span>
                </div>
            </div>
        @endif

        @if ($session->zones->isNotEmpty())
            <div class="flex h-2.5 overflow-hidden rounded-full">
                @foreach ($session->zones as $zone)
                    <div
                        class="h-full"
                        style="width: {{ max((float) $zone->percentage_value, 0) }}%; background: {{ $zone->swatch() }}"
                        title="{{ $zone->name }} {{ $zone->percentage_label }}"
                    ></div>
                @endforeach
            </div>
            <ul class="grid grid-cols-2 gap-1.5 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($session->zones as $zone)
                    <li @class([
                        'flex items-start gap-2 rounded-lg bg-base-200 px-2 py-1.5',
                        'opacity-45' => (int) $zone->duration_seconds === 0,
                    ])>
                        <span
                            class="mt-0.5 inline-block h-2.5 w-2.5 shrink-0 rounded-full"
                            style="background: {{ $zone->swatch() }}"
                        ></span>
                        <div class="min-w-0">
                            <p class="truncate text-xs font-semibold leading-tight">{{ $zone->shortName() }}</p>
                            <p class="text-[11px] leading-tight text-base-content/60">{{ $zone->bpm_label }}</p>
                            <p class="text-[11px] leading-tight tabular-nums text-base-content/70">
                                {{ $zone->duration_label ?: '0:00' }}
                                <span class="text-base-content/40">·</span>
                                {{ $zone->percentage_label ?: '0%' }}
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endif
