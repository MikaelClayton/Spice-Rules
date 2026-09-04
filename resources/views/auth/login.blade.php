@extends('layouts.guest')

@section('title', 'Log in — '.config('app.name'))

@section('content')
    <div class="card w-full max-w-md bg-base-100 shadow-xl">
        <div class="card-body">
            <h1 class="card-title text-2xl">Welcome back</h1>
            <p class="text-base-content/70">Log in to continue to {{ config('app.name') }}.</p>

            @if (session('status'))
                <div role="alert" class="alert alert-success">
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div role="alert" class="alert alert-error">
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="mt-2 space-y-4">
                @csrf

                <fieldset class="fieldset">
                    <label class="label" for="email">Email</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        class="input w-full @error('email') input-error @enderror"
                        required
                        autofocus
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
                        autocomplete="current-password"
                    >
                </fieldset>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label class="label cursor-pointer justify-start gap-3">
                        <input type="checkbox" name="remember" class="checkbox checkbox-primary" {{ old('remember') ? 'checked' : '' }}>
                        Remember me
                    </label>
                    <a href="{{ route('password.request') }}" class="link link-primary text-sm">Forgot password?</a>
                </div>

                <button type="submit" class="btn btn-primary w-full">Log in</button>
            </form>

            <p class="pt-2 text-center text-sm text-base-content/70">
                Don't have an account?
                <a href="{{ route('register') }}" class="link link-primary">Sign up</a>
            </p>
        </div>
    </div>
@endsection
