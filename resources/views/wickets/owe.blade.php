<section class="card bg-base-100 shadow-xl mb-5">
    <div class="card-body gap-3 p-4 sm:p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">You owe</p>
        <dl
            @class([
                'grid gap-3',
                'grid-cols-1' => $mySpecialCounts->isEmpty(),
                'grid-cols-2' => $mySpecialCounts->isNotEmpty(),
                'sm:grid-cols-3' => $mySpecialCounts->count() === 2,
                'sm:grid-cols-4' => $mySpecialCounts->count() >= 3,
            ])
        >
            <div class="rounded-box bg-base-200 p-4">
                <dt class="text-xs font-semibold uppercase tracking-wide text-base-content/50">🍺 Sips</dt>
                <dd class="mt-1 text-3xl font-bold leading-none tabular-nums">{{ $hideOwnFines ? '?' : $myRemainingSips }}</dd>
            </div>
            @foreach ($mySpecialCounts as $special)
                <div class="rounded-box bg-base-200 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-base-content/50">
                        {{ $special['type']->emoji() }} {{ $special['type']->label() }}
                    </dt>
                    <dd class="mt-1 text-3xl font-bold leading-none tabular-nums">{{ $special['count'] }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>
