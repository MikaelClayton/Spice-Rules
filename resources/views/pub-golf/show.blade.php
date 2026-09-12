@extends('layouts.app')

@section('title', $crawl->name.' — Pub Golf — '.config('app.name'))

@section('content')
    <div class="min-w-0 pb-24" data-pub-golf-board data-joined-at="{{ $board['joined_at_iso'] }}">
        <div class="mb-4 flex items-center justify-between gap-2">
            <a href="{{ route('pub-golf.index') }}" class="btn btn-ghost btn-sm -ml-2 shrink-0">← Back</a>
            <button type="button" class="btn btn-ghost btn-sm min-w-0 font-mono tracking-widest" data-copy="{{ $crawl->join_code }}">
                Code {{ $crawl->join_code }}
            </button>
        </div>

        <div class="mb-5">
            <h1 class="text-2xl font-bold sm:text-3xl">{{ $crawl->name }}</h1>
            <p class="mt-1 text-base-content/70">
                {{ $board['active_count'] }} {{ $board['active_count'] === 1 ? 'person still out' : 'people still out' }}
                @if ($board['left_count'] > 0)
                    · {{ $board['left_count'] }} called it
                @endif
            </p>
        </div>

        @if (session('status'))
            <div role="alert" class="alert alert-success mb-5">
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="alert alert-error mb-5">
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <div class="mb-3 grid grid-cols-3 gap-2 sm:gap-3">
            <div class="card bg-base-100 shadow-md">
                <div class="card-body gap-1 p-3 sm:p-4">
                    <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-base-content/50 sm:text-xs">Drinks</p>
                    <p class="text-2xl font-bold tabular-nums leading-none sm:text-3xl">{{ $board['my_alcoholic'] }}</p>
                </div>
            </div>
            <div class="card bg-base-100 shadow-md">
                <div class="card-body gap-1 p-3 sm:p-4">
                    <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-base-content/50 sm:text-xs">Per hour</p>
                    <p class="text-2xl font-bold tabular-nums leading-none sm:text-3xl">{{ $board['pace']['drinks_per_hour'] }}</p>
                </div>
            </div>
            <div class="card bg-base-100 shadow-md">
                <div class="card-body gap-1 p-3 sm:p-4">
                    <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-base-content/50 sm:text-xs">Out for</p>
                    <p class="text-2xl font-bold tabular-nums leading-none sm:text-3xl" data-duration>{{ $board['pace']['duration_label'] }}</p>
                </div>
            </div>
        </div>

        <div class="card mb-5 bg-base-100 shadow-md">
            <div class="card-body gap-2 p-3 sm:p-4">
                <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-base-content/50 sm:text-xs">Pace</p>
                @include('pub-golf.pace', ['pace' => $board['pace']])
            </div>
        </div>

        <div class="grid min-w-0 gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(18rem,0.9fr)]">
            <section class="card min-w-0 self-start bg-base-100 shadow-xl">
                <div class="card-body min-w-0 gap-3 overflow-x-clip p-3 sm:gap-4 sm:p-5">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="card-title">Log a drink</h2>
                        <div class="flex flex-wrap gap-2">
                            @if ($board['last_listed']?->isListed)
                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    data-confirm-log
                                    data-drink-key="{{ $board['last_listed']->key }}"
                                    data-drink-label="{{ $board['last_listed']->label }}"
                                    @if ($board['last_listed']->imageUrl) data-drink-photo="{{ $board['last_listed']->imageUrl }}" @endif
                                >
                                    Same again
                                </button>
                            @endif
                            @if ($board['last_drink'])
                                <form method="POST" action="{{ route('pub-golf.drinks.undo', $crawl) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm">Undo</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    @if ($board['favorites'] !== [])
                        <div class="flex flex-wrap gap-2">
                            @foreach ($board['favorites'] as $favorite)
                                <button
                                    type="button"
                                    class="btn btn-sm"
                                    data-confirm-log
                                    data-drink-key="{{ $favorite->key }}"
                                    data-drink-label="{{ $favorite->label }}"
                                    @if ($favorite->imageUrl) data-drink-photo="{{ $favorite->imageUrl }}" @endif
                                >
                                    {{ $favorite->category->emoji() }} {{ $favorite->label }}
                                </button>
                            @endforeach
                        </div>
                    @endif

                    @if ($drinks !== [])
                        <label class="sr-only" for="pub-golf-search">Search drinks</label>
                        <input
                            id="pub-golf-search"
                            type="search"
                            data-drink-search
                            class="input input-md w-full"
                            placeholder="Search Drinks"
                            autocomplete="off"
                        >

                        <div class="min-w-0 overflow-x-auto pb-1" data-drink-filters>
                            <div class="flex w-max gap-2">
                                <button type="button" class="btn btn-xs sm:btn-sm btn-primary shrink-0" data-drink-filter="" aria-pressed="true">All</button>
                                @foreach ($drinks as $categoryKey => $group)
                                    @php $category = \App\Enums\PubGolfDrinkCategory::from($categoryKey); @endphp
                                    <button type="button" class="btn btn-xs sm:btn-sm btn-ghost shrink-0" data-drink-filter="{{ $category->value }}" aria-pressed="false">
                                        {{ $category->emoji() }} {{ $category->label() }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2" data-drink-grid>
                            @foreach ($drinks as $group)
                                @foreach ($group as $drink)
                                    <div
                                        class="relative min-w-0"
                                        data-drink-tile
                                        data-drink-name="{{ strtolower($drink->label) }}"
                                        data-drink-category="{{ $drink->category->value }}"
                                    >
                                        @if ($drink->customId !== null && $drink->createdByUserId === $viewerId)
                                            <button
                                                type="button"
                                                class="btn btn-circle btn-xs absolute top-1.5 right-1.5 z-10 border-0 bg-base-100/90 text-base-content/70 shadow-sm"
                                                aria-label="Remove {{ $drink->label }}"
                                                data-confirm-remove
                                                data-drink-label="{{ $drink->label }}"
                                                data-remove-action="{{ route('pub-golf.custom-drinks.destroy', [$crawl, $drink->customId]) }}"
                                            >
                                                ×
                                            </button>
                                        @endif
                                        <button
                                            type="button"
                                            class="flex w-full min-w-0 items-center gap-3 overflow-hidden rounded-2xl border border-base-300 bg-base-200 p-2 text-left sm:flex-col sm:items-stretch sm:gap-0 sm:p-0"
                                            data-confirm-log
                                            data-drink-key="{{ $drink->key }}"
                                            data-drink-label="{{ $drink->label }}"
                                            @if ($drink->imageUrl) data-drink-photo="{{ $drink->imageUrl }}" @endif
                                        >
                                            @if ($drink->imageUrl)
                                                <img
                                                    src="{{ $drink->imageUrl }}"
                                                    alt=""
                                                    class="h-14 w-14 shrink-0 rounded-xl object-cover sm:h-28 sm:w-full sm:rounded-none"
                                                >
                                            @else
                                                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-base-300 text-2xl leading-none sm:h-28 sm:w-full sm:rounded-none sm:text-4xl" aria-hidden="true">{{ $drink->category->emoji() }}</span>
                                            @endif
                                            <span class="line-clamp-2 min-w-0 pr-8 text-sm font-semibold leading-tight sm:min-h-10 sm:px-2 sm:py-2 sm:pr-2 sm:text-center">{{ $drink->label }}</span>
                                        </button>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                        <p class="hidden text-sm text-base-content/60" data-drink-empty>No drinks match that search.</p>
                    @else
                        <p class="text-sm text-base-content/70">No drinks yet. Add one below.</p>
                    @endif

                    <form
                        method="POST"
                        action="{{ route('pub-golf.custom-drinks.store', $crawl) }}"
                        enctype="multipart/form-data"
                        class="min-w-0 max-w-full space-y-3 rounded-xl border border-dashed border-base-300 p-3"
                        data-add-drink
                    >
                        @csrf
                        <p class="font-semibold">Don't see your drink?</p>
                        <p class="text-sm text-base-content/70">Add it here with a photo and a category so everyone on this crawl can log it.</p>
                        <div class="grid min-w-0 gap-3 sm:grid-cols-[minmax(0,1fr)_10rem]">
                            <fieldset class="fieldset min-w-0 py-0">
                                <label class="label" for="custom-drink-name">Drink name</label>
                                <input
                                    id="custom-drink-name"
                                    type="text"
                                    name="name"
                                    value="{{ old('name') }}"
                                    class="input w-full min-w-0 @error('name') input-error @enderror"
                                    maxlength="80"
                                    placeholder="Windhoek Light"
                                    required
                                >
                            </fieldset>
                            <fieldset class="fieldset min-w-0 py-0">
                                <label class="label" for="custom-drink-category">Category</label>
                                <select
                                    id="custom-drink-category"
                                    name="category"
                                    class="select w-full min-w-0 @error('category') select-error @enderror"
                                    required
                                >
                                    <option value="" disabled @selected(old('category') === null || old('category') === '')>Pick one</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->value }}" @selected(old('category') === $category->value)>
                                            {{ $category->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </fieldset>
                        </div>
                        <fieldset class="fieldset min-w-0 py-0">
                            <label class="label" for="custom-drink-photo">Photo</label>
                            <input
                                id="custom-drink-photo"
                                type="file"
                                name="photo"
                                accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                                class="file-input w-full min-w-0 max-w-full @error('photo') file-input-error @enderror"
                                data-drink-photo
                                required
                            >
                            <p class="pt-1 text-xs leading-snug text-base-content/60">Take a photo or upload a JPEG, PNG, or WebP. HEIC is not allowed.</p>
                            <p class="hidden text-sm text-error" data-drink-photo-error></p>
                        </fieldset>
                        <button type="submit" class="btn btn-outline btn-sm">Add drink</button>
                    </form>
                </div>
            </section>

            <div class="space-y-4">
                <section class="card bg-base-100 shadow-xl">
                    <div class="card-body p-4 sm:p-5">
                        <h2 class="card-title">Leaderboard</h2>
                        <ol class="mt-2 space-y-2">
                            @foreach ($board['standings'] as $index => $row)
                                <li class="flex items-center justify-between gap-3 rounded-xl px-2 py-1.5 {{ $row['is_you'] ? 'bg-secondary/20' : '' }}">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="w-5 text-sm font-bold tabular-nums text-base-content/50">{{ $index + 1 }}</span>
                                        <span class="h-3 w-3 shrink-0 rounded-full" style="background: {{ $row['color'] }}"></span>
                                        <span class="truncate font-medium">
                                            {{ $row['name'] }}
                                            @if ($row['is_you'])
                                                <span class="text-base-content/50">(you)</span>
                                            @endif
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @unless ($row['still_in'])
                                            <span class="badge badge-ghost badge-sm">Called it</span>
                                        @endunless
                                        <span class="font-bold tabular-nums">{{ $row['alcoholic'] }}</span>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </section>

                <section class="card bg-base-100 shadow-xl">
                    <div class="card-body p-4 sm:p-5">
                        <h2 class="card-title">Tonight</h2>
                        @if ($board['activity'] === [])
                            <p class="text-sm text-base-content/70">Nothing logged yet. First round is on you.</p>
                        @else
                            <ul class="space-y-2">
                                @foreach ($board['activity'] as $item)
                                    <li class="flex items-start justify-between gap-3 text-sm">
                                        <span>
                                            <span class="font-medium">{{ $item['is_you'] ? 'You' : $item['name'] }}</span>
                                            logged {{ $item['emoji'] }} {{ $item['label'] }}
                                        </span>
                                        <span class="shrink-0 tabular-nums text-base-content/50">{{ $item['created_at'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </section>

                <section class="card bg-base-100 shadow-xl">
                    <div class="card-body gap-3 p-4 sm:p-5">
                        <h2 class="card-title">Call it</h2>
                        <p class="text-sm text-base-content/70">
                            This only ends your night. Everyone still out keeps logging until they leave too.
                        </p>
                        <button type="button" class="btn btn-error w-full" onclick="document.getElementById('pub-golf-leave').showModal()">
                            End my crawl
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <dialog id="pub-golf-leave" class="modal">
        <div class="modal-box">
            <h3 class="text-lg font-bold">End your crawl?</h3>
            <p class="py-3 text-base-content/70">
                You will get a recap of what you drank and how long you lasted. The crawl stays open until the last person calls it.
            </p>
            <div class="modal-action">
                <form method="dialog">
                    <button class="btn btn-ghost">Keep going</button>
                </form>
                <form method="POST" action="{{ route('pub-golf.leave.store', $crawl) }}">
                    @csrf
                    <button type="submit" class="btn btn-error">End my crawl</button>
                </form>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <dialog id="pub-golf-log" class="modal">
        <div class="modal-box">
            <h3 class="text-lg font-bold">Log this drink?</h3>
            <div class="py-3">
                <img data-confirm-log-photo alt="" class="mx-auto mb-3 hidden h-28 w-28 rounded-2xl object-cover">
                <p class="text-base-content/70">
                    Add <span class="font-semibold text-base-content" data-confirm-log-name></span> to your night.
                </p>
            </div>
            <div class="modal-action">
                <form method="dialog">
                    <button class="btn btn-ghost">Cancel</button>
                </form>
                <form method="POST" action="{{ route('pub-golf.drinks.store', $crawl) }}">
                    @csrf
                    <input type="hidden" name="drink" value="" data-confirm-log-value>
                    <button type="submit" class="btn btn-primary">Log it</button>
                </form>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    <dialog id="pub-golf-remove" class="modal">
        <div class="modal-box">
            <h3 class="text-lg font-bold">Remove this drink?</h3>
            <p class="py-3 text-base-content/70">
                <span class="font-semibold text-base-content" data-confirm-remove-name></span> will come off the list for everyone. Nights that already logged it keep it on their recap.
            </p>
            <div class="modal-action">
                <form method="dialog">
                    <button class="btn btn-ghost">Keep it</button>
                </form>
                <form method="POST" action="#" data-confirm-remove-form>
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-error">Remove drink</button>
                </form>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>

    @include('pub-golf.chat')
@endsection
