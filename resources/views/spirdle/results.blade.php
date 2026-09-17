@php
    $results = $results ?? collect();
    $ranks = $ranks ?? [];
    $hasPlayed = (bool) ($hasPlayed ?? false);
    $emptyTitle = $emptyTitle ?? "Today's results";
    $emptyCopy = $emptyCopy ?? "Nobody has finished today's Spirdle yet.";
    $winners = $results->filter(fn ($play) => $play->won);
    $fewestGuesses = $winners->count() >= 2 ? $winners->min('guess_count') : null;
    $fastestMs = $winners->count() >= 2 ? $winners->min('duration_ms') : null;
    $fewestInvalid = $winners->count() >= 2 ? $winners->min('invalid_word_count') : null;
    $reviewQuery = $reviewQuery ?? [];
@endphp

@if ($results->isEmpty())
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title">{{ $emptyTitle }}</h2>
            <p class="text-base-content/70">{{ $emptyCopy }}</p>
        </div>
    </div>
@else
    <ol class="space-y-2.5 sm:space-y-3">
        @foreach ($results as $index => $result)
            @php
                $place = $ranks[$result->id] ?? ($index + 1);
                $isYou = $result->user_id === Auth::id();
                $rewards = [];

                if ($result->won && $fewestGuesses !== null && $result->guess_count === $fewestGuesses) {
                    $rewards[] = [
                        'emoji' => '🎯',
                        'label' => 'Fewest guesses',
                        'message' => '🎯 Fewest guesses · '.$result->guess_count,
                    ];
                }

                if ($result->won && $fastestMs !== null && $result->duration_ms === $fastestMs) {
                    $rewards[] = [
                        'emoji' => '⚡',
                        'label' => 'Fastest',
                        'message' => '⚡ Fastest · '.$result->durationLabel(),
                    ];
                }

                if ($result->won && $fewestInvalid !== null && $result->invalid_word_count === $fewestInvalid) {
                    $rewards[] = [
                        'emoji' => '✨',
                        'label' => 'Cleanest typing',
                        'message' => '✨ Fewest invalid words · '.$result->invalid_word_count,
                    ];
                }
            @endphp
            <li class="card relative bg-base-100 shadow-md {{ $isYou ? 'ring-2 ring-primary' : '' }} {{ $hasPlayed ? 'transition hover:shadow-lg' : '' }}">
                @if ($hasPlayed)
                    <a
                        href="{{ route('spirdle.plays.show', ['spirdlePlay' => $result] + $reviewQuery) }}"
                        class="absolute inset-0 z-0 rounded-[inherit]"
                        aria-label="See how {{ $result->user?->name }} played"
                    ></a>
                @endif
                <div @class(['card-body relative z-10 p-3.5 sm:p-4', 'pointer-events-none' => $hasPlayed])>
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
                            <div class="flex min-w-0 items-center gap-1">
                                <p class="truncate font-semibold leading-tight">{{ $result->user?->name }}</p>
                                @foreach ($rewards as $reward)
                                    <button
                                        type="button"
                                        class="pointer-events-auto inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-base leading-none hover:bg-base-200 active:scale-95"
                                        data-reward="{{ $reward['message'] }}"
                                        aria-label="{{ $reward['label'] }}"
                                    >{{ $reward['emoji'] }}</button>
                                @endforeach
                            </div>
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
                        @if ($hasPlayed)
                            @include('spirdle.mini-grid', ['guesses' => $result->normalizedGuesses()])
                        @endif
                    </div>
                </div>
            </li>
        @endforeach
    </ol>
@endif
