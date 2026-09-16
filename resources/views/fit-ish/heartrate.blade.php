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
            <div class="grid grid-cols-[2rem_minmax(0,1fr)] gap-x-1.5 gap-y-1 sm:grid-cols-[2.5rem_minmax(0,1fr)] sm:gap-x-2">
                <div class="flex h-36 flex-col justify-between py-0.5 text-right text-[10px] leading-none text-base-content/55 tabular-nums sm:h-44">
                    @foreach ($chart['yTicks'] as $tick)
                        <span>{{ $tick }}</span>
                    @endforeach
                </div>
                <div class="relative h-36 min-w-0 sm:h-44">
                    @foreach ($chart['gridMinutes'] as $minute)
                        <div
                            class="pointer-events-none absolute inset-y-0 z-10 border-l border-dashed border-base-content/20"
                            style="left: {{ round(100 * $minute / $chart['endMinute'], 2) }}%"
                        ></div>
                    @endforeach
                    <div class="absolute inset-0 z-20 flex items-stretch gap-[3px] px-px">
                        @foreach ($chart['columns'] as $column)
                            <div class="relative min-w-0 flex-1" title="{{ $column['label'] }}">
                                @if ($column['recorded'])
                                    @foreach ($column['segments'] as $segment)
                                        <div
                                            class="absolute inset-x-0"
                                            style="bottom: {{ $segment['bottom'] }}%; height: {{ $segment['height'] }}%; background: {{ $segment['color'] }}"
                                        ></div>
                                    @endforeach
                                @elseif ($column['trailingEmpty'])
                                    <span class="absolute bottom-1 left-1/2 -translate-x-1/2 text-[10px] leading-none tracking-tighter text-base-content/35">··</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                <div></div>
                <div class="relative h-4 text-[10px] tabular-nums text-base-content/55">
                    @foreach ($chart['xTicks'] as $tick)
                        <span
                            @class([
                                'absolute',
                                '-translate-x-full' => $tick === $chart['endMinute'],
                                '-translate-x-1/2' => $tick !== $chart['endMinute'],
                            ])
                            style="left: {{ round(100 * $tick / $chart['endMinute'], 2) }}%"
                        >{{ $tick }}</span>
                    @endforeach
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
