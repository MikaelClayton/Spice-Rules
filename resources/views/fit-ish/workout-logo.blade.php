@php
    $src = $src ?? null;
    $color = $color ?? null;
    $box = $box ?? 'h-10 w-10 rounded-lg';
@endphp

@if (filled($src))
    <div
        data-workout-logo
        @class([
            'shrink-0 overflow-hidden',
            $box,
            'bg-base-300' => ! is_string($color) || $color === '',
        ])
        @if (is_string($color) && $color !== '')
            style="background: {{ $color }}"
        @endif
    >
        <img src="{{ $src }}" alt="" class="h-full w-full object-contain p-1">
    </div>
@endif
