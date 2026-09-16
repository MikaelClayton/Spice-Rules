@php
    $isYou = $session->user_id === Auth::id();
    $showName = $showName ?? true;
    $place = $place ?? null;
    $rewards = [];

    if (($mostCalories ?? null) !== null && $session->estimated_calories === $mostCalories) {
        $rewards[] = ['emoji' => '🔥', 'label' => 'Most calories'];
    }

    if (($highestAverageHr ?? null) !== null && $session->average_heartrate === $highestAverageHr) {
        $rewards[] = ['emoji' => '❤️', 'label' => 'Highest average HR'];
    }

    if (($mostHardSeconds ?? null) !== null && $session->hardZoneSeconds() === $mostHardSeconds) {
        $rewards[] = ['emoji' => '⚡', 'label' => 'Most time in high zones'];
    }
@endphp

<li class="card bg-base-100 shadow-md {{ $isYou ? 'ring-2 ring-primary' : '' }}">
    <div class="card-body gap-3 p-3.5 sm:p-4">
        <div class="flex items-start gap-3">
            @if ($place !== null)
                <span
                    @class([
                        'badge badge-md sm:badge-lg mt-0.5 shrink-0 tabular-nums',
                        'badge-warning' => $place === 1,
                        'badge-ghost' => $place === 2,
                        'badge-accent' => $place === 3,
                        'badge-neutral' => $place > 3,
                    ])
                >{{ $place }}</span>
            @endif
            <div class="min-w-0 flex-1">
                @if ($showName)
                    <p class="flex min-w-0 items-center gap-2 font-semibold leading-tight">
                        <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $session->user?->boardColor() }}"></span>
                        <span class="truncate">{{ $session->user?->name ?? 'Unknown' }}</span>
                        @foreach ($rewards as $reward)
                            <span class="tooltip" data-tip="{{ $reward['label'] }}">{{ $reward['emoji'] }}</span>
                        @endforeach
                    </p>
                @endif
                <div class="mt-1 flex min-w-0 items-center gap-2">
                    @include('fit-ish.workout-logo', [
                        'src' => $session->workout?->logoUrl(),
                        'color' => $session->user?->boardColor(),
                        'box' => 'h-10 w-10 rounded-lg',
                    ])
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ $session->workout?->display_name ?? 'Workout' }}</p>
                        <p class="text-xs text-base-content/60">
                            {{ $session->studio?->name ?? 'Studio' }}
                            @if ($session->class_time)
                                <span class="text-base-content/30">·</span>
                                {{ \Illuminate\Support\Carbon::parse($session->class_time)->format('g:ia') }}
                            @endif
                            @if ($session->workout?->type)
                                <span class="text-base-content/30">·</span>
                                {{ $session->workout->type }}
                            @endif
                        </p>
                    </div>
                </div>
            </div>
            <p class="shrink-0 text-right">
                <span class="text-2xl font-bold tabular-nums leading-none">{{ number_format($session->pointsValue(), 1) }}</span>
                <span class="mt-1 block text-xs text-base-content/50">pts</span>
            </p>
        </div>

        <dl class="grid grid-cols-3 gap-2 text-center text-xs sm:text-sm">
            <div class="rounded-lg bg-base-200 px-2 py-2">
                <dt class="text-base-content/50">Avg HR</dt>
                <dd class="mt-0.5 font-semibold tabular-nums">
                    {{ $session->average_heartrate ?? '—' }}
                    @if ($session->average_heartrate)
                        <span class="font-normal text-base-content/50">bpm</span>
                    @endif
                </dd>
            </div>
            <div class="rounded-lg bg-base-200 px-2 py-2">
                <dt class="text-base-content/50">Max HR</dt>
                <dd class="mt-0.5 font-semibold tabular-nums">
                    {{ $session->max_heartrate ?? '—' }}
                    @if ($session->max_heartrate)
                        <span class="font-normal text-base-content/50">bpm</span>
                    @endif
                </dd>
            </div>
            <div class="rounded-lg bg-base-200 px-2 py-2">
                <dt class="text-base-content/50">kcal</dt>
                <dd class="mt-0.5 font-semibold tabular-nums">{{ $session->estimated_calories ?? '—' }}</dd>
            </div>
        </dl>

        @if ($showHeartrate ?? true)
            @include('fit-ish.heartrate')
        @endif
    </div>
</li>
