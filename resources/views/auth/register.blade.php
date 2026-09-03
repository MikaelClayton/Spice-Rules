@extends('layouts.guest')

@section('title', 'Sign up — '.config('app.name'))

@section('content')
    <div class="card w-full max-w-md bg-base-100 shadow-xl">
        <div class="card-body">
            <h1 class="card-title text-2xl">Create an account</h1>
            <p class="text-base-content/70">Join {{ config('app.name') }} to get started.</p>

            @if ($errors->any())
                <div role="alert" class="alert alert-error">
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}" class="mt-2 space-y-4">
                @csrf

                <fieldset class="fieldset">
                    <label class="label" for="name">Name</label>
                    <input
                        id="name"
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        class="input w-full @error('name') input-error @enderror"
                        required
                        autofocus
                        autocomplete="name"
                    >
                </fieldset>

                <fieldset class="fieldset">
                    <label class="label" for="email">Email</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        class="input w-full @error('email') input-error @enderror"
                        required
                        autocomplete="username"
                    >
                </fieldset>

                <fieldset class="fieldset">
                    <label class="label" for="password">Password</label>
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

                <button type="submit" class="btn btn-primary w-full">Sign up</button>
            </form>

            <p class="pt-2 text-center text-sm text-base-content/70">
                Already have an account?
                <a href="{{ route('login') }}" class="link link-primary">Log in</a>
            </p>
        </div>
    </div>
@endsection
