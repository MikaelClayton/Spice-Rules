@if ($sessions->isEmpty())
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title">{{ ($isToday ?? true) ? "Today's classes" : 'Classes' }}</h2>
            <p class="text-base-content/70">
                Nobody has a Lionheart class logged {{ ($isToday ?? true) ? 'for today' : 'for this day' }} yet.
            </p>
        </div>
    </div>
@else
    <ol class="space-y-2.5 sm:space-y-3">
        @foreach ($sessions as $index => $session)
            @php
                $place = $ranks[$session->id] ?? ($index + 1);
            @endphp
            @include('fit-ish.session-card', [
                'session' => $session,
                'place' => $place,
                'showName' => true,
                'mostCalories' => $mostCalories,
                'highestAverageHr' => $highestAverageHr,
                'mostHardSeconds' => $mostHardSeconds,
            ])
        @endforeach
    </ol>
@endif
