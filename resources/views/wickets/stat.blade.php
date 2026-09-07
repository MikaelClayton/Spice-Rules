<span
    class="flex shrink-0 flex-col items-center"
    title="{{ $label }}"
    data-stat-type="{{ $type }}"
    data-stat-count="{{ $count }}"
>
    <span class="text-base leading-none" aria-hidden="true">{{ $emoji }}</span>
    <span class="mt-0.5 text-sm font-bold leading-none tabular-nums">{{ $count }}</span>
    <span class="sr-only">{{ $reader ?? $count.' '.$label }}</span>
</span>
