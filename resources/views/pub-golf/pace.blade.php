@php
    $level = (int) ($pace['level'] ?? 0);
    $steps = $pace['steps'] ?? [];
    $tone = $pace['tone'] ?? 'neutral';
    $fill = match ($tone) {
        'info' => 'bg-info',
        'success' => 'bg-success',
        'warning' => 'bg-warning',
        'secondary' => 'bg-secondary',
        'primary' => 'bg-primary',
        default => 'bg-base-content/40',
    };
@endphp

<div class="flex min-w-0 flex-col gap-2" data-pace>
    <div
        class="flex h-2.5 gap-1"
        role="meter"
        aria-label="Pace"
        aria-valuemin="0"
        aria-valuemax="{{ max(count($steps) - 1, 0) }}"
        aria-valuenow="{{ $level }}"
        aria-valuetext="{{ $pace['label'] ?? '' }}"
    >
        @foreach ($steps as $index => $step)
            <span
                class="min-w-0 flex-1 rounded-full {{ $index <= $level ? $fill : 'bg-base-300' }}"
                title="{{ $step }}"
            ></span>
        @endforeach
    </div>
    <div class="flex items-baseline justify-between gap-2">
        <p class="text-lg font-bold leading-none sm:text-xl">{{ $pace['label'] ?? '' }}</p>
        @if (count($steps) > 1)
            <p class="text-[0.65rem] text-base-content/50">{{ $steps[0] }} → {{ $steps[array_key_last($steps)] }}</p>
        @endif
    </div>
</div>
