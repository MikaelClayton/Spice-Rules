@php
    $atLeast = $at_least ?? false;
    $display = $atLeast ? '>= '.$count : $count;
    $fineType = \App\Enums\WicketFineType::tryFrom((string) $type);
    $typeLabel = $fineType?->label() ?? $label;
    $explanation = $fineType?->description() ?? $label;
    $reader = $reader ?? ($atLeast ? 'at least '.$count.' '.$label : $count.' '.$label);
@endphp
<div class="dropdown dropdown-end" data-stat-explain>
    <button
        type="button"
        tabindex="0"
        class="flex shrink-0 cursor-pointer flex-col items-center rounded-lg"
        title="{{ $typeLabel }}"
        aria-label="{{ $reader }}. {{ $explanation }}"
        data-stat-type="{{ $type }}"
        data-stat-count="{{ $count }}"
        data-stat-bound="{{ $atLeast ? 'at-least' : ($count === '?' ? 'hidden' : 'exact') }}"
        onclick="event.stopPropagation()"
    >
        <span class="text-base leading-none" aria-hidden="true">{{ $emoji }}</span>
        <span class="mt-0.5 text-sm font-bold leading-none whitespace-nowrap tabular-nums">{{ $display }}</span>
        <span class="sr-only">{{ $reader }}</span>
    </button>
    <div tabindex="0" class="dropdown-content z-50 w-44 rounded-box bg-base-100 p-2.5 text-left shadow-lg">
        <p class="text-sm font-semibold leading-tight">{{ $emoji }} {{ $typeLabel }}</p>
        <p class="mt-1 text-xs leading-snug text-base-content/70">{{ $explanation }}</p>
    </div>
</div>
