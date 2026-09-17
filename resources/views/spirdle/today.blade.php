@if ($puzzle === null)
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title">Today's Spirdle</h2>
            <p class="text-base-content/70">Today's word is still being ground. Check back after midnight.</p>
        </div>
    </div>
@else
    @if (! $hasPlayed)
        <section class="card bg-base-100 shadow-xl">
            <div class="card-body gap-3 p-4 sm:p-5">
                <h2 class="card-title">{{ $viewerPlay ? 'Continue today' : "Today's Spirdle" }}</h2>
                <p class="text-base-content/70">
                    @if ($viewerPlay)
                        You started today's word. Finish it to land on the board.
                    @else
                        Six tries, same word as everyone else. Timer starts when you open the board.
                    @endif
                </p>
                <div class="card-actions">
                    <a href="{{ route('spirdle.play') }}" class="btn btn-primary">
                        {{ $viewerPlay ? 'Continue' : 'Play now' }}
                    </a>
                </div>
            </div>
        </section>
    @endif

    <div class="mt-4">
        @include('spirdle.results')
    </div>
@endif
