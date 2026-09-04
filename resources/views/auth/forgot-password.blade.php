@extends('layouts.guest')

@section('title', 'Forgot password — '.config('app.name'))

@section('content')
    <div class="card w-full max-w-md bg-base-100 shadow-xl">
        <div class="card-body">
            <h1 class="card-title text-2xl">Forgot password</h1>
            <p class="text-base-content/70">Enter your email and we will send you a reset link.</p>

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

            <form method="POST" action="{{ route('password.email') }}" class="mt-2 space-y-4">
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

                <button type="submit" class="btn btn-primary w-full">Send reset link</button>
            </form>

            <p class="pt-2 text-center text-sm text-base-content/70">
                Remembered it?
                <a href="{{ route('login') }}" class="link link-primary">Log in</a>
            </p>
        </div>
    </div>
@endsection
