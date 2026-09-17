@php
    $guesses = $guesses ?? [];
@endphp

<div class="grid shrink-0 grid-rows-6 gap-px" aria-hidden="true">
    @for ($row = 0; $row < 6; $row++)
        <div class="grid grid-cols-5 gap-px">
            @for ($col = 0; $col < 5; $col++)
                @php
                    $tile = $guesses[$row]['tiles'][$col] ?? null;
                @endphp
                <span @class([
                    'block h-2 w-2 rounded-[2px] sm:h-2.5 sm:w-2.5',
                    'bg-success' => $tile === 'correct',
                    'bg-secondary' => $tile === 'present',
                    'bg-accent' => $tile === 'absent',
                    'bg-base-300' => $tile === null,
                ])></span>
            @endfor
        </div>
    @endfor
</div>
