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

                <button type="submit" class="btn btn-primary btn-lg w-full">Create group</button>
            </form>
        </div>
    </div>
@endsection
