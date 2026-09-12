<section class="card bg-base-100 shadow-xl">
    <div class="card-body gap-4 p-4 sm:p-5">
        <div>
            <h2 class="card-title">Drink sips</h2>
            <p class="text-sm text-base-content/70">Log what you just drank to knock sips off your fines.</p>
        </div>

        @if ($hideOwnFines)
            <p class="text-sm text-base-content/70">Your sip fines are hidden in this tournament.</p>
            <form method="POST" action="{{ route('wickets.sips.store', $group) }}" class="space-y-2">
                @csrf
                <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">How many sips?</p>
                <div class="grid grid-cols-4 gap-2">
                    @foreach ([1, 2, 3, 4] as $count)
                        <button type="submit" name="sips" value="{{ $count }}" class="btn btn-lg h-16 text-xl tabular-nums">
                            {{ $count }}
                        </button>
                    @endforeach
                </div>
            </form>
        @elseif ($mySipFines->isNotEmpty())
            <ul class="space-y-2">
                @foreach ($mySipFines as $fine)
                    <li class="rounded-xl bg-base-200 px-3 py-2.5">
                        <p class="font-semibold tabular-nums">{{ $fine->remainingSips() }} {{ $fine->remainingSips() === 1 ? 'sip' : 'sips' }}</p>
                        <p class="text-sm text-base-content/70">{{ $fine->displayReason() }}</p>
                    </li>
                @endforeach
            </ul>
        @endif

        @unless ($hideOwnFines)
            @if ($myRemainingSips > 0)
                <form method="POST" action="{{ route('wickets.sips.store', $group) }}" class="space-y-2">
                    @csrf
                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">How many sips?</p>
                    <div class="grid grid-cols-4 gap-2">
                        @foreach ([1, 2, 3, 4] as $count)
                            @if ($count <= $myRemainingSips)
                                <button type="submit" name="sips" value="{{ $count }}" class="btn btn-lg h-16 text-xl tabular-nums">
                                    {{ $count }}
                                </button>
                            @endif
                        @endforeach
                    </div>
                    @if ($myRemainingSips > 4)
                        <button type="submit" name="sips" value="{{ $myRemainingSips }}" class="btn btn-primary btn-lg w-full">
                            Drink the rest ({{ $myRemainingSips }})
                        </button>
                    @endif
                </form>
            @elseif ($mySpecials->isNotEmpty())
                <p class="text-sm text-base-content/70">No sip fines left. Specials still count until you mark them done.</p>
            @else
                <p class="text-sm text-base-content/70">You're clear. For now.</p>
            @endif
        @endunless
    </div>
</section>

<section class="card bg-base-100 shadow-xl">
    <div class="card-body gap-3 p-4 sm:p-5">
        <div>
            <h2 class="card-title">Specials</h2>
            <p class="text-sm text-base-content/70">Down downs, funnels, and shoeys get ticked off here.</p>
        </div>

        @if ($hideOwnFines)
            <p class="text-sm text-base-content/70">Your specials are hidden in this tournament.</p>
        @else
            @forelse ($mySpecials as $special)
                <article class="rounded-xl bg-base-200 p-3.5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $special->type->emoji() }} {{ $special->displayLabel() }}</p>
                            <p class="mt-0.5 text-sm text-base-content/70">{{ $special->displayReason() }}</p>
                        </div>
                        <form method="POST" action="{{ route('wickets.fines.completions.store', [$group, $special]) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">Done</button>
                        </form>
                    </div>
                </article>
            @empty
                <p class="text-sm text-base-content/70">No down downs, funnels, or shoeys outstanding.</p>
            @endforelse
        @endif
    </div>
</section>
