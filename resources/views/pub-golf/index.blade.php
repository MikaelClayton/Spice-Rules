@extends('layouts.app')

@section('title', 'Pub Golf — '.config('app.name'))

@section('content')
    <div class="mb-4">
        <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm -ml-2">← Back</a>
    </div>

    <div class="mb-5">
        <h1 class="text-2xl font-bold sm:text-3xl">Pub Golf</h1>
        <p class="mt-1 text-base-content/70">Start a crawl, log every drink, and call it when you are done. The night stays open until the last person leaves, or until nobody has logged, joined, or left for four hours.</p>
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

    @if ($current)
        <a href="{{ route('pub-golf.show', $current) }}" class="card bg-primary text-primary-content shadow-xl mb-5 transition hover:-translate-y-0.5 hover:shadow-2xl">
            <div class="card-body">
                <p class="text-xs font-semibold uppercase tracking-wide text-primary-content/70">You are on a crawl</p>
                <h2 class="card-title">{{ $current->name }}</h2>
                <p class="text-primary-content/80">Code {{ $current->join_code }} · tap to keep logging</p>
                <div class="card-actions justify-end">
                    <span class="btn bg-primary-content text-primary">Back to the crawl</span>
                </div>
            </div>
        </a>
    @else
        <div class="grid gap-4 sm:grid-cols-2 mb-8">
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title">Start a crawl</h2>
                    <p class="text-sm text-base-content/70">You get a code. Friends join on their phones and log their own drinks.</p>
                    <form method="POST" action="{{ route('pub-golf.store') }}" class="space-y-3">
                        @csrf
                        <fieldset class="fieldset">
                            <label class="label" for="name">Name (optional)</label>
                            <input
                                id="name"
                                type="text"
                                name="name"
                                value="{{ old('name') }}"
                                class="input w-full @error('name') input-error @enderror"
                                maxlength="80"
                                placeholder="Friday in Observatory"
                            >
                        </fieldset>
                        <button type="submit" class="btn btn-primary w-full">Start crawl</button>
                    </form>
                </div>
            </div>

            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title">Join a crawl</h2>
                    <p class="text-sm text-base-content/70">Ask the starter for the 6-character code, or scan their QR.</p>
                    <form method="POST" action="{{ route('pub-golf.joins.store') }}" class="space-y-3" data-join-form>
                        @csrf
                        <fieldset class="fieldset">
                            <label class="label" for="code">Crawl code</label>
                            <input
                                id="code"
                                type="text"
                                name="code"
                                value="{{ old('code') }}"
                                class="input w-full font-mono uppercase tracking-[0.3em] @error('code') input-error @enderror"
                                maxlength="8"
                                autocapitalize="characters"
                                autocomplete="off"
                                placeholder="AB3K7Q"
                                data-join-code-input
                            >
                        </fieldset>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <button type="button" class="btn btn-outline w-full" data-join-scan-open>Scan QR</button>
                            <button type="submit" class="btn btn-secondary w-full">Join crawl</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <dialog id="pub-golf-join-scan" class="modal" data-join-scan>
        <div class="modal-box max-w-md">
            <h3 class="text-lg font-bold">Scan crawl QR</h3>
            <p class="mt-1 text-sm text-base-content/70">Point your camera at the host’s code.</p>
            <div class="relative mt-4 overflow-hidden rounded-2xl bg-neutral">
                <video class="aspect-square w-full object-cover" playsinline muted data-join-scan-video></video>
                <div class="pointer-events-none absolute inset-8 rounded-xl border-2 border-white/70"></div>
            </div>
            <p class="mt-3 text-sm text-base-content/60" data-join-scan-status>Starting camera…</p>
            <div class="modal-action">
                <form method="dialog">
                    <button class="btn btn-ghost" data-join-scan-close>Cancel</button>
                </form>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button data-join-scan-close>close</button>
        </form>
    </dialog>

    <section>
        <h2 class="mb-3 text-lg font-semibold">Your nights</h2>
        @if ($past->isEmpty())
            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <p class="text-base-content/70">Recaps land here after you call it — duration, drinks, and drinks per hour.</p>
                </div>
            </div>
        @else
            <ul class="grid gap-3">
                @foreach ($past as $participant)
                    @continue($participant->crawl === null)
                    <li>
                        <a href="{{ route('pub-golf.recap.show', $participant->crawl) }}" class="card bg-base-100 shadow-md transition hover:-translate-y-0.5 hover:shadow-xl">
                            <div class="card-body p-4 sm:p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="card-title text-lg">{{ $participant->crawl->name }}</h3>
                                        <p class="mt-1 text-sm text-base-content/70">
                                            {{ $participant->left_at?->timezone($displayTimezone)->format('j M Y, g:i A') }}
                                        </p>
                                    </div>
                                    @if ($participant->crawl->isOpen())
                                        <span class="badge badge-secondary shrink-0">Still going</span>
                                    @else
                                        <span class="badge badge-ghost shrink-0">Wrapped</span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
