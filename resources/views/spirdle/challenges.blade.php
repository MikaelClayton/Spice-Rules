@if ($dailies === [])
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title">Challenges</h2>
            <p class="text-base-content/70">Finish a daily Spirdle to unlock that recap.</p>
        </div>
    </div>
@else
    <section class="card bg-base-100 shadow-xl">
        <div class="card-body gap-3 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Daily</p>
            <div class="flex gap-2 overflow-x-auto pb-1" data-challenge-list>
                @foreach ($dailies as $daily)
                    <button
                        type="button"
                        class="btn btn-ghost btn-sm shrink-0 {{ ! empty($daily['locked']) ? 'opacity-50' : '' }}"
                        data-challenge-token="{{ $daily['token'] }}"
                        data-locked="{{ ! empty($daily['locked']) ? 'true' : 'false' }}"
                    >
                        {{ $daily['label'] }}
                    </button>
                @endforeach
            </div>
        </div>
    </section>

    <section class="card bg-base-100 shadow-xl">
        <div class="card-body p-4">
            <h2 class="card-title text-base" data-challenge-title>Challenge</h2>
            <p class="text-sm text-base-content/70" data-challenge-copy>Attempts, missed tries, times, and the word.</p>
            <p class="mt-4 text-center text-4xl font-bold uppercase tracking-[0.35em]" data-challenge-word></p>
        </div>
    </section>

    <div data-challenge-results>
        @foreach ($dailies as $daily)
            <div hidden data-challenge-results-panel="{{ $daily['token'] }}">
                @include('spirdle.results', [
                    'results' => $daily['board']['results'],
                    'ranks' => $daily['board']['ranks'],
                    'hasPlayed' => true,
                    'emptyTitle' => 'Results',
                    'emptyCopy' => 'No results for this daily yet.',
                    'reviewQuery' => [
                        'tab' => 'challenges',
                        'challenge' => $daily['token'],
                    ],
                ])
            </div>
        @endforeach
    </div>
@endif
<div class="toast toast-top toast-end z-[2000]" data-challenge-toast></div>
