@if ($results->isEmpty())
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title">Today's results</h2>
            <p class="text-base-content/70">Nobody has logged a score yet today.</p>
        </div>
    </div>
@else
    <ol class="space-y-2.5 sm:space-y-3">
        @foreach ($results as $index => $result)
            @php
                $place = $ranks[$result->id] ?? ($index + 1);
                $isYou = $result->geoguesser?->user_id === Auth::id();
                $name = $result->geoguesser?->user?->name ?? $result->geoguesser?->username;
                $nick = $result->geoguesser?->username;
                $rewards = [];

                if ($result->is_done_as_team) {
                    $rewards[] = [
                        'emoji' => '🤝',
                        'label' => 'Played as a team',
                        'message' => '🤝 Played as a team',
                    ];
                } else {
                    if ($closestDistance !== null && $result->total_distance === $closestDistance) {
                        $rewards[] = [
                            'emoji' => '💪',
                            'label' => 'Closest to target',
                            'message' => '💪 Closest to target · '.number_format($result->total_distance / 1000, 1).' km',
                        ];
                    }

                    if ($furthestDistance !== null && $result->total_distance === $furthestDistance) {
                        $rewards[] = [
                            'emoji' => '💩',
                            'label' => 'Furthest from target',
                            'message' => '💩 Furthest from target · '.number_format($result->total_distance / 1000, 1).' km',
                        ];
                    }

                    if ($fewestSteps !== null && $result->total_steps_count === $fewestSteps) {
                        $rewards[] = [
                            'emoji' => '♿',
                            'label' => 'Least steps',
                            'message' => '♿ Least steps · '.number_format($result->total_steps_count),
                        ];
                    }

                    if ($mostSteps !== null && $result->total_steps_count === $mostSteps) {
                        $rewards[] = [
                            'emoji' => '🏃',
                            'label' => 'Most steps',
                            'message' => '🏃 Most steps · '.number_format($result->total_steps_count),
                        ];
                    }
                }
            @endphp
            <li class="card bg-base-100 shadow-md {{ $isYou ? 'ring-2 ring-primary' : '' }}">
                <div class="card-body p-3.5 sm:p-4">
                    <div class="flex items-start gap-3">
                        <span
                            data-today-rank="{{ $place }}"
                            @class([
                                'badge badge-md sm:badge-lg mt-0.5 shrink-0 tabular-nums',
                                'badge-warning' => $place === 1,
                                'badge-ghost' => $place === 2,
                                'badge-accent' => $place === 3,
                                'badge-neutral' => $place > 3,
                            ])
                        >{{ $place }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex min-w-0 items-center gap-1">
                                <p class="truncate font-semibold leading-tight">{{ $name }}</p>
                                @foreach ($rewards as $reward)
                                    <button
                                        type="button"
                                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-base leading-none hover:bg-base-200 active:scale-95"
                                        data-reward="{{ $reward['message'] }}"
                                        aria-label="{{ $reward['label'] }}"
                                    >{{ $reward['emoji'] }}</button>
                                @endforeach
                            </div>
                            @if ($nick && $nick !== $name)
                                <p class="mt-0.5 truncate text-xs text-base-content/55">{{ $nick }}</p>
                            @endif
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-lg font-bold leading-none tabular-nums sm:text-xl">{{ number_format($result->total_score) }}</p>
                            <p class="mt-1.5 text-xs tabular-nums text-base-content/60">
                                @if ($result->total_distance)
                                    {{ number_format($result->total_distance / 1000, 1) }} km
                                @else
                                    Distance pending
                                @endif
                            </p>
                            <p class="text-xs tabular-nums text-base-content/60">
                                @if ($result->total_steps_count)
                                    {{ number_format($result->total_steps_count) }} steps
                                @else
                                    Steps pending
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </li>
        @endforeach
    </ol>
@endif
