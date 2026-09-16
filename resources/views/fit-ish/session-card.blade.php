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

<li class="card min-w-0 bg-base-100 shadow-md {{ $isYou ? 'ring-2 ring-primary' : '' }}">
    <div class="card-body gap-3 p-3 sm:p-4">
        <div class="flex items-start gap-2.5">
            @if ($place !== null)
                <span
                    @class([
                        'badge badge-sm mt-0.5 shrink-0 tabular-nums sm:badge-lg',
                        'badge-warning' => $place === 1,
                        'badge-ghost' => $place === 2,
                        'badge-accent' => $place === 3,
                        'badge-neutral' => $place > 3,
                    ])
                >{{ $place }}</span>
            @endif
            <div class="min-w-0 flex-1 space-y-2">
                <div class="flex items-start justify-between gap-3">
                    @if ($showName)
                        <p class="flex min-w-0 flex-1 flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[15px] font-semibold leading-snug sm:text-base">
                            <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $session->user?->boardColor() }}"></span>
                            <span>{{ $session->user?->name ?? 'Unknown' }}</span>
                            @foreach ($rewards as $reward)
                                <span title="{{ $reward['label'] }}" aria-label="{{ $reward['label'] }}">{{ $reward['emoji'] }}</span>
                            @endforeach
                        </p>
                    @else
                        <p class="min-w-0 flex-1 text-[15px] font-semibold leading-snug sm:text-base">{{ $session->workout?->display_name ?? 'Workout' }}</p>
                    @endif
                    <p class="shrink-0 pt-0.5 text-right leading-none">
                        <span class="text-xl font-bold tabular-nums sm:text-2xl">{{ number_format($session->pointsValue(), 1) }}</span>
                        <span class="mt-1 block text-[10px] uppercase tracking-wide text-base-content/50">pts</span>
                    </p>
                </div>

                <div class="flex items-start gap-2.5">
                    @include('fit-ish.workout-logo', [
                        'src' => $session->workout?->logoUrl(),
                        'color' => $session->user?->boardColor(),
                        'box' => 'h-12 w-12 rounded-xl sm:h-11 sm:w-11 sm:rounded-lg',
                    ])
                    <div class="min-w-0 flex-1">
                        @if ($showName)
                            <p class="text-sm font-medium leading-snug sm:text-base">{{ $session->workout?->display_name ?? 'Workout' }}</p>
                        @endif
                        @if ($session->studio?->name)
                            <p class="mt-0.5 text-xs leading-snug text-base-content/65">{{ $session->studio->name }}</p>
                        @endif
                        <p class="mt-0.5 text-xs text-base-content/55">
                            @if ($session->class_time)
                                {{ \Illuminate\Support\Carbon::parse($session->class_time)->format('g:ia') }}
                            @endif
                            @if ($session->class_time && $session->workout?->type)
                                <span class="text-base-content/30">·</span>
                            @endif
                            @if ($session->workout?->type)
                                {{ $session->workout->type }}
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <dl class="grid grid-cols-3 gap-1.5 text-center sm:gap-2 sm:text-sm">
            <div class="rounded-lg bg-base-200 px-1.5 py-2 sm:px-2">
                <dt class="text-[10px] uppercase tracking-wide text-base-content/50 sm:text-xs sm:normal-case sm:tracking-normal">Avg HR</dt>
                <dd class="mt-0.5 text-sm font-semibold tabular-nums sm:text-base">
                    {{ $session->average_heartrate ?? '—' }}
                    @if ($session->average_heartrate)
                        <span class="text-[10px] font-normal text-base-content/50 sm:text-xs">bpm</span>
                    @endif
                </dd>
            </div>
            <div class="rounded-lg bg-base-200 px-1.5 py-2 sm:px-2">
                <dt class="text-[10px] uppercase tracking-wide text-base-content/50 sm:text-xs sm:normal-case sm:tracking-normal">Max HR</dt>
                <dd class="mt-0.5 text-sm font-semibold tabular-nums sm:text-base">
                    {{ $session->max_heartrate ?? '—' }}
                    @if ($session->max_heartrate)
                        <span class="text-[10px] font-normal text-base-content/50 sm:text-xs">bpm</span>
                    @endif
                </dd>
            </div>
            <div class="rounded-lg bg-base-200 px-1.5 py-2 sm:px-2">
                <dt class="text-[10px] uppercase tracking-wide text-base-content/50 sm:text-xs sm:normal-case sm:tracking-normal">kcal</dt>
                <dd class="mt-0.5 text-sm font-semibold tabular-nums sm:text-base">{{ $session->estimated_calories ?? '—' }}</dd>
            </div>
        </dl>

        @if ($showHeartrate ?? true)
            @include('fit-ish.heartrate')
        @endif
    </div>
</li>
