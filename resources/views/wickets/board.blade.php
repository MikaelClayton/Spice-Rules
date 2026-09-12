@forelse ($standings as $row)
    <details data-player="{{ $row['user']->id }}" class="card group bg-base-100 shadow-md {{ $row['user']->id === Auth::id() ? 'ring-2 ring-primary' : '' }}">
        <summary class="card-body cursor-pointer list-none p-3.5 sm:p-4 [&::-webkit-details-marker]:hidden">
            <div class="flex items-center gap-3">
                <span class="inline-block h-3 w-3 shrink-0 rounded-full" style="background: {{ $row['user']->boardColor() }}"></span>
                <p class="min-w-0 flex-1 truncate font-semibold leading-tight">
                    {{ $row['user']->name }}
                </p>
                <div class="flex shrink-0 items-center gap-3">
                    @if ($row['showSips'])
                        @include('wickets.stat', [
                            'emoji' => '🍺',
                            'count' => $row['sips'],
                            'label' => $row['sips'] === 1 ? 'sip' : 'sips',
                            'type' => 'sips',
                            'at_least' => $row['sips_at_least'] ?? false,
                        ])
                    @endif
                    @foreach ($row['specials'] as $special)
                        @include('wickets.stat', [
                            'emoji' => $special['type']->emoji(),
                            'count' => $special['count'],
                            'label' => $special['type']->label(),
                            'type' => $special['type']->value,
                            'at_least' => $special['at_least'] ?? false,
                        ])
                    @endforeach
                </div>
                <span class="shrink-0 text-base-content/40 transition group-open:rotate-180" aria-hidden="true">▾</span>
            </div>
        </summary>
        <div class="card-body border-t border-base-300 px-5 py-4 sm:px-6" data-player-fines="{{ $row['user']->id }}">
            @if ($row['finesHidden'])
                <p class="text-sm text-base-content/60">Your fines are hidden.</p>
            @else
                @forelse ($row['fines'] as $fine)
                    <div class="flex items-center justify-between gap-3 border-t border-base-300 py-3 first:border-t-0 first:pt-0 last:pb-0" data-open-fine-id="{{ $fine->id }}">
                        <div class="min-w-0">
                            <p class="text-sm text-base-content/70">{{ $fine->displayReason() }}</p>
                            <p class="mt-0.5 text-xs text-base-content/50">from {{ $fine->issuedBy?->name ?? 'Unknown' }}</p>
                        </div>
                        @include('wickets.stat', [
                            'emoji' => $fine->type->emoji(),
                            'count' => $fine->type->isSip() ? $fine->remainingSips() : 1,
                            'label' => $fine->type->isSip()
                                ? ($fine->remainingSips() === 1 ? 'sip' : 'sips')
                                : $fine->type->label(),
                            'type' => $fine->type->value,
                        ])
                    </div>
                @empty
                    <p class="text-sm text-base-content/60">No open fines.</p>
                @endforelse
            @endif
        </div>
    </details>
@empty
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title">Nobody here yet</h2>
            <p class="text-base-content/70">Add players on the People tab.</p>
        </div>
    </div>
@endforelse

<section class="card bg-base-100 shadow-xl">
    <div class="card-body p-4">
        <h2 class="card-title text-base">Activity</h2>
        @forelse ($activity as $item)
            @if ($item['kind'] === 'drink')
                <div class="flex items-center justify-between gap-3 border-t border-base-300 py-3 first:border-t-0 first:pt-1">
                    <div class="min-w-0">
                        <p class="font-semibold leading-tight">{{ $item['log']->user?->name ?? 'Unknown' }}</p>
                        <p class="mt-0.5 text-sm text-base-content/70">
                            Drank {{ $item['log']->sips === 1 ? '1 sip' : $item['log']->sips.' sips' }}
                        </p>
                        <p class="mt-1 text-xs text-base-content/50">{{ $item['occurred_at']?->diffForHumans() }}</p>
                    </div>
                    @include('wickets.stat', [
                        'emoji' => '🍺',
                        'count' => $item['log']->sips,
                        'label' => $item['log']->sips === 1 ? 'sip' : 'sips',
                        'type' => 'sips',
                    ])
                </div>
            @elseif ($item['kind'] === 'special_done')
                <div class="flex items-center justify-between gap-3 border-t border-base-300 py-3 first:border-t-0 first:pt-1">
                    <div class="min-w-0">
                        <p class="font-semibold leading-tight">{{ $item['fine']->issuedTo?->name ?? 'Unknown' }}</p>
                        <p class="mt-0.5 text-sm text-base-content/70">{{ $item['fine']->displayReason() }}</p>
                        <p class="mt-1 text-xs text-base-content/50">Done · {{ $item['occurred_at']?->diffForHumans() }}</p>
                    </div>
                    @include('wickets.stat', [
                        'emoji' => $item['fine']->type->emoji(),
                        'count' => 1,
                        'label' => $item['fine']->type->label(),
                        'type' => $item['fine']->type->value,
                    ])
                </div>
            @else
                <div class="flex items-center justify-between gap-3 border-t border-base-300 py-3 first:border-t-0 first:pt-1">
                    <div class="min-w-0">
                        <p class="font-semibold leading-tight">
                            {{ $item['fine']->issuedTo?->name ?? 'Unknown' }}
                            <span class="font-medium text-base-content/50">from {{ $item['fine']->issuedBy?->name ?? 'Unknown' }}</span>
                        </p>
                        <p class="mt-0.5 text-sm text-base-content/70">{{ $item['fine']->displayReason() }}</p>
                        <p class="mt-1 text-xs text-base-content/50">{{ $item['occurred_at']?->diffForHumans() }}</p>
                    </div>
                    @include('wickets.stat', [
                        'emoji' => $item['fine']->type->emoji(),
                        'count' => $item['fine']->type->isSip() ? $item['fine']->sips_owed : 1,
                        'label' => $item['fine']->type->isSip()
                            ? ($item['fine']->sips_owed === 1 ? 'sip' : 'sips')
                            : $item['fine']->type->label(),
                        'type' => $item['fine']->type->value,
                    ])
                </div>
            @endif
        @empty
            <p class="text-sm text-base-content/70">Nothing yet. Someone's about to have a big night.</p>
        @endforelse
    </div>
</section>
