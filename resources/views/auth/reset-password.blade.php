@extends('layouts.guest')

@section('title', 'Reset password — '.config('app.name'))

@section('content')
    <div class="card w-full max-w-md bg-base-100 shadow-xl">
        <div class="card-body">
            <h1 class="card-title text-2xl">Reset password</h1>
            <p class="text-base-content/70">Choose a new password for your Spice Rules account.</p>

            @if ($errors->any())
                <div role="alert" class="alert alert-error">
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="mt-2 space-y-4">
                @csrf

                <input type="hidden" name="token" value="{{ $token }}">

                <fieldset class="fieldset">
                    <label class="label" for="email">Email</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email', $email) }}"
                        class="input w-full @error('email') input-error @enderror"
                        required
                        autofocus
                        autocomplete="username"
                    >
                </fieldset>

                <fieldset class="fieldset">
                    <label class="label" for="password">New password</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        class="input w-full @error('password') input-error @enderror"
                        required
                        autocomplete="new-password"
                    >
                </fieldset>

                <fieldset class="fieldset">
                    <label class="label" for="password_confirmation">Confirm password</label>
                    <input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        class="input w-full"
                        required
                        autocomplete="new-password"
                    >
                </fieldset>

                <button type="submit" class="btn btn-primary w-full">Reset password</button>
            </form>
        </div>
    </div>
@endsection
