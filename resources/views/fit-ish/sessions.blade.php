@if ($sessionDates === [])
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title">Sessions</h2>
            <p class="text-base-content/70">No Lionheart sessions synced yet. Press Sync on your Fit-Ish profile tab.</p>
        </div>
    </div>
@else
    <section class="card bg-base-100 shadow-xl">
        <div class="card-body gap-3 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Daily</p>
            <div class="flex gap-2 overflow-x-auto pb-1" data-session-dates>
                @foreach ($sessionDates as $day)
                    <button
                        type="button"
                        @class([
                            'btn btn-sm shrink-0',
                            'btn-primary' => $day['date'] === $selectedSessionDate,
                            'btn-ghost' => $day['date'] !== $selectedSessionDate,
                        ])
                        data-session-date="{{ $day['date'] }}"
                        aria-pressed="{{ $day['date'] === $selectedSessionDate ? 'true' : 'false' }}"
                    >
                        {{ $day['label'] }}
                    </button>
                @endforeach
            </div>
        </div>
    </section>

    <div data-session-day data-loaded-date="{{ $sessionDay['date'] ?? '' }}">
        @if ($sessionDay !== null)
            @include('fit-ish.today', $sessionDay)
        @endif
    </div>
@endif
