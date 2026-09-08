@php
    $atLeast = $at_least ?? false;
    $display = $atLeast ? '>= '.$count : $count;
    $reader = $reader ?? ($atLeast ? 'at least '.$count.' '.$label : $count.' '.$label);
@endphp
<span
    class="flex shrink-0 flex-col items-center"
    title="{{ $atLeast ? 'At least '.$count.' '.$label : $label }}"
    data-stat-type="{{ $type }}"
    data-stat-count="{{ $count }}"
    data-stat-bound="{{ $atLeast ? 'at-least' : ($count === '?' ? 'hidden' : 'exact') }}"
>
    <span class="text-base leading-none" aria-hidden="true">{{ $emoji }}</span>
    <span class="mt-0.5 text-sm font-bold leading-none whitespace-nowrap tabular-nums">{{ $display }}</span>
    <span class="sr-only">{{ $reader }}</span>
</span>
