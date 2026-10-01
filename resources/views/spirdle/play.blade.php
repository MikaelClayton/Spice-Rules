@extends('layouts.app')

@section('title', (! empty($mode) && $mode === 'practice' ? 'Practice Spirdle' : 'Play Spirdle').' — '.config('app.name'))
@section('hideSupportWidget')
@endsection
@section('playLayout')
@endsection
@section('bodyClass', 'h-dvh max-h-dvh overflow-hidden')

@section('content')
    @php
        $readonly = ! empty($readonly);
        $backUrl = $backUrl ?? route('spirdle.index');
        $isPractice = ($mode ?? 'daily') === 'practice';
    @endphp
    <div class="mb-1 flex shrink-0 items-center justify-between gap-3">
        <a href="{{ $backUrl }}" class="btn btn-ghost btn-sm -ml-2">← Back</a>
        @if ($readonly && filled($playerName ?? null))
            <p class="min-w-0 truncate text-sm font-semibold">{{ $playerName }}</p>
        @elseif ($isPractice)
            <p class="min-w-0 truncate text-sm font-semibold">Practice</p>
        @endif
        <p class="text-sm tabular-nums text-base-content/60" data-spirdle-clock>
            @if ($game['finished'] && $game['durationLabel'])
                {{ $game['durationLabel'] }}
            @else
                0s
            @endif
        </p>
    </div>

    <div
        class="relative mx-auto flex min-h-0 w-full flex-1 flex-col gap-2 overflow-hidden"
        data-spirdle-play
        @unless ($readonly)
            @if ($isPractice)
                data-guess-url="{{ route('spirdle.practice.guesses.store') }}"
                data-pause-url="{{ route('spirdle.practice.pause') }}"
                data-resume-url="{{ route('spirdle.practice.resume') }}"
                data-words-url="{{ asset('spirdle-guesses.txt') }}"
                data-replay-url="{{ route('spirdle.practice') }}"
            @else
                data-guess-url="{{ route('spirdle.guesses.store') }}"
                data-pause-url="{{ route('spirdle.pause') }}"
                data-resume-url="{{ route('spirdle.resume') }}"
                data-words-url="{{ asset('spirdle-guesses.txt') }}"
                data-board-url="{{ route('spirdle.index') }}"
            @endif
        @else
            data-readonly="true"
        @endunless
    >
            <div class="mx-auto grid w-full shrink-0 grid-rows-6" data-board>
                @for ($row = 0; $row < 6; $row++)
                    @php $guess = $game['guesses'][$row] ?? null; @endphp
                    <div class="grid min-h-0 grid-cols-5 gap-[5px]" data-row>
                        @for ($col = 0; $col < 5; $col++)
                            @php
                                $letter = $guess['word'][$col] ?? '';
                                $tile = $guess['tiles'][$col] ?? '';
                            @endphp
                            <div
                                @class([
                                    'spirdle-tile',
                                    'is-filled' => $letter !== '',
                                    'is-correct' => $tile === 'correct',
                                    'is-present' => $tile === 'present',
                                    'is-absent' => $tile === 'absent',
                                ])
                                data-tile
                            >{{ $letter !== '' ? strtoupper($letter) : '' }}</div>
                        @endfor
                    </div>
                @endfor
            </div>

            @if ($isPractice)
                <div class="flex shrink-0 flex-col items-center gap-2" hidden data-spirdle-practice-end>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em]" data-spirdle-result></p>
                    <a href="{{ route('spirdle.practice') }}" class="btn btn-primary btn-sm" data-spirdle-replay>
                        Practice again
                    </a>
                </div>
            @endif

            @unless ($readonly)
                <div class="grid shrink-0 gap-[6px]" data-keyboard>
                    @foreach ([
                        ['q', 'w', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p'],
                        ['a', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'l'],
                        ['Enter', 'z', 'x', 'c', 'v', 'b', 'n', 'm', 'Backspace'],
                    ] as $index => $keys)
                        <div @class(['flex justify-center gap-[6px]', 'px-3' => $index === 1])>
                            @foreach ($keys as $key)
                                <button
                                    type="button"
                                    @class([
                                        'spirdle-key',
                                        'spirdle-key-wide' => in_array($key, ['Enter', 'Backspace'], true),
                                    ])
                                    data-key="{{ $key }}"
                                    aria-label="{{ $key === 'Backspace' ? 'Backspace' : $key }}"
                                >
                                    @if ($key === 'Backspace')
                                        ⌫
                                    @else
                                        {{ $key === 'Enter' ? 'Enter' : strtoupper($key) }}
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endunless

            <div class="toast toast-top toast-center z-[2000]" data-spirdle-toast></div>
    </div>

    <script type="application/json" data-spirdle-game>@json($game)</script>
@endsection
