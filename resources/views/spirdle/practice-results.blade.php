@php
    $results = $results ?? collect();
    $ranks = $ranks ?? [];
@endphp

@if ($results->isEmpty())
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title">Practice results</h2>
            <p class="text-base-content/70">Finish a practice round to see your grids here.</p>
        </div>
    </div>
@else
    <ol class="space-y-2.5 sm:space-y-3">
        @foreach ($results as $index => $result)
            @php
                $place = $ranks[$result->id] ?? ($index + 1);
                $label = $result->finished_at?->timezone(config('app.timezone'))->format('D j M')
                    ?? 'Practice';
            @endphp
            <li class="card relative bg-base-100 shadow-md transition hover:shadow-lg">
                <a
                    href="{{ route('spirdle.practices.show', $result) }}"
                    class="absolute inset-0 z-0 rounded-[inherit]"
                    aria-label="See practice from {{ $label }}"
                ></a>
                <div class="card-body relative z-10 pointer-events-none p-3.5 sm:p-4">
                    <div class="flex items-start gap-3">
                        <span
                            @class([
                                'badge badge-md sm:badge-lg mt-0.5 shrink-0 tabular-nums',
                                'badge-warning' => $place === 1,
                                'badge-ghost' => $place === 2,
                                'badge-accent' => $place === 3,
                                'badge-neutral' => $place > 3,
                            ])
                        >{{ $place }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold leading-tight">{{ $label }}</p>
                            <p class="mt-0.5 text-xs tabular-nums text-base-content/60">
                                @if ($result->won)
                                    {{ $result->guess_count }} {{ \Illuminate\Support\Str::plural('guess', $result->guess_count) }}
                                @else
                                    Missed
                                @endif
                                @if ($result->durationLabel())
                                    <span class="text-base-content/30">·</span>
                                    {{ $result->durationLabel() }}
                                @endif
                                <span class="text-base-content/30">·</span>
                                {{ $result->invalid_word_count }} invalid
                            </p>
                        </div>
                        @include('spirdle.mini-grid', ['guesses' => $result->normalizedGuesses()])
                    </div>
                </div>
            </li>
        @endforeach
    </ol>
@endif
