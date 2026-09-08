@extends('layouts.app')

@section('title', 'New group — Wickets — '.config('app.name'))

@section('content')
    <div class="mb-4">
        <a href="{{ route('wickets.index') }}" class="btn btn-ghost btn-sm -ml-2">← Back</a>
    </div>

    <div class="mb-5">
        <h1 class="text-2xl font-bold sm:text-3xl">New group</h1>
        <p class="mt-1 text-base-content/70">Name the event, then add people to it.</p>
    </div>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            @if ($errors->any())
                <div role="alert" class="alert alert-error">
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('wickets.store') }}" class="space-y-4">
                @csrf

                <fieldset class="fieldset">
                    <label class="label" for="name">Group name</label>
                    <input
                        id="name"
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        class="input w-full @error('name') input-error @enderror"
                        required
                        maxlength="80"
                        autofocus
                        placeholder="Saturday at the club"
                    >
                </fieldset>

                <div class="flex items-start gap-3">
                    <input type="hidden" name="is_tournament" value="0">
                    <input
                        id="is_tournament"
                        type="checkbox"
                        name="is_tournament"
                        value="1"
                        class="toggle toggle-primary mt-0.5 shrink-0"
                        @checked(old('is_tournament'))
                    >
                    <label for="is_tournament" class="min-w-0 flex-1 cursor-pointer">
                        <span class="font-medium">Tournament</span>
                        <span class="mt-0.5 block text-sm font-normal whitespace-normal text-base-content/70">
                            Players can't see their own fines — only what they gave.
                        </span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-full">Create group</button>
            </form>
        </div>
    </div>
@endsection
