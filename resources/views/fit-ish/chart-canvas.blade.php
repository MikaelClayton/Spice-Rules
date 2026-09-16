@if (($payload['datasets'] ?? []) !== [])
    <section class="card min-w-0 bg-base-100 shadow-md">
        <div class="card-body gap-2 p-3 sm:p-4">
            <h2 class="text-sm font-semibold leading-tight">{{ $title }}</h2>
            @if (! empty($caption))
                <p class="text-xs leading-snug text-base-content/55">{{ $caption }}</p>
            @endif
            <div @class([
                'relative w-full min-w-0',
                'h-56 sm:h-64' => ($type ?? '') === 'radar',
                'h-52 sm:h-64' => ($type ?? '') !== 'radar',
            ])>
                <div data-fit-ish-chart="{{ $type }}" class="absolute inset-0 min-h-0 min-w-0">
                    <canvas></canvas>
                    <script type="application/json">@json($payload)</script>
                </div>
            </div>
            @if ($showLegend ?? false)
                <ul class="flex flex-wrap justify-center gap-x-3 gap-y-1.5">
                    @foreach ($payload['datasets'] as $dataset)
                        <li class="flex min-w-0 items-center gap-1.5 text-xs">
                            <span
                                class="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
                                style="background: {{ $dataset['color'] }}"
                            ></span>
                            <span class="leading-snug">{{ $dataset['label'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endif
