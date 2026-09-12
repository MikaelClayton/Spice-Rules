@php
    $pickerId = $pickerId ?? 'people';
    $searchId = 'wicket-people-search-'.$pickerId;
    $listId = 'wicket-people-options-'.$pickerId;
    $selectedIds = collect($selectedIds ?? [])->map(fn ($id): int => (int) $id);
@endphp
<div data-people-picker class="relative">
    <div class="flex min-h-12 flex-wrap items-center gap-1.5 rounded-field border border-base-300 bg-base-100 px-2 py-1.5">
        <div data-people-chips class="flex flex-wrap gap-1.5 empty:hidden">
            @foreach ($people as $person)
                @if ($selectedIds->contains($person->id))
                    <span class="badge badge-secondary max-w-[10rem] gap-1">
                        <span class="truncate">{{ $person->name }}</span>
                    </span>
                @endif
            @endforeach
        </div>
        <input
            id="{{ $searchId }}"
            type="search"
            data-people-search
            autocomplete="off"
            autocorrect="off"
            spellcheck="false"
            role="combobox"
            aria-autocomplete="list"
            aria-controls="{{ $listId }}"
            aria-expanded="false"
            aria-haspopup="listbox"
            placeholder="Search players…"
            class="min-h-9 min-w-[8rem] flex-1 bg-transparent px-1 text-base outline-none placeholder:text-base-content/40"
        >
    </div>
    <ul
        id="{{ $listId }}"
        data-people-list
        role="listbox"
        class="absolute z-40 mt-1 max-h-60 w-full overflow-y-auto rounded-box border border-base-300 bg-base-100 p-1 shadow-lg"
    >
        @foreach ($people as $person)
            <li
                data-people-option
                data-name="{{ str($person->name)->lower() }}"
                data-label="{{ $person->name }}"
                role="option"
            >
                <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-base-200 has-[:checked]:bg-base-200">
                    <input
                        type="checkbox"
                        name="{{ $inputName }}"
                        value="{{ $person->id }}"
                        class="checkbox checkbox-primary checkbox-sm"
                        @checked($selectedIds->contains($person->id))
                    >
                    <span class="inline-block h-3 w-3 shrink-0 rounded-full" style="background: {{ $person->boardColor() }}"></span>
                    <span class="min-w-0 flex-1 truncate font-medium">{{ $person->name }}</span>
                </label>
            </li>
        @endforeach
        <li data-people-empty hidden class="px-3 py-2 text-sm text-base-content/60">No matching players</li>
    </ul>
</div>
