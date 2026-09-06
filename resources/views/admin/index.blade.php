@extends('layouts.app')

@section('title', 'Admin — '.config('app.name'))

@php
    $tab = ($errors->has('push') || $activeTab === 'push') ? 'push' : 'overview';
@endphp

@section('content')
    <div class="mb-5">
        <h1 class="text-3xl font-bold">Admin</h1>
        <p class="mt-1 text-base-content/70">Tools that only you can see.</p>
    </div>

    @if (session('status'))
        <div role="alert" class="alert alert-success mb-6">
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="tabs tabs-box tabs-lg w-full" data-tabs data-default-tab="push">
        <input
            type="radio"
            name="admin_tabs"
            class="tab grow"
            aria-label="Push notifications"
            data-tab="push"
            @checked($tab === 'push')
        >
        <div class="tab-content mt-4">
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title">Push notifications</h2>
                    <p class="text-base-content/70">
                        Send a test ping to check that Firebase, this browser, and the server can talk to each other.
                    </p>

                    @if ($errors->has('push') || $errors->has('audience') || $errors->has('title') || $errors->has('body'))
                        <div role="alert" class="alert alert-error">
                            <span>{{ $errors->first('push') ?: $errors->first() }}</span>
                        </div>
                    @endif

                    @if (! $firebaseConfigured)
                        <div role="alert" class="alert alert-warning">
                            <span>Firebase is not fully configured on this server, so test pings cannot be sent.</span>
                        </div>
                    @endif

                    <p class="text-sm text-base-content/70">
                        Your devices: <span class="font-semibold">{{ $myDeviceCount }}</span>
                        <span class="text-base-content/40">·</span>
                        All devices: <span class="font-semibold">{{ $deviceCount }}</span>
                    </p>

                    <form method="POST" action="{{ route('admin.push.send') }}" class="space-y-4">
                        @csrf

                        <fieldset class="fieldset">
                            <legend class="label">Send to</legend>
                            <label class="label cursor-pointer justify-start gap-3">
                                <input
                                    type="radio"
                                    name="audience"
                                    value="me"
                                    class="radio radio-primary"
                                    @checked(old('audience', 'me') === 'me')
                                >
                                <span>Just me</span>
                            </label>
                            <label class="label cursor-pointer justify-start gap-3">
                                <input
                                    type="radio"
                                    name="audience"
                                    value="everyone"
                                    class="radio radio-primary"
                                    @checked(old('audience') === 'everyone')
                                >
                                <span>Everyone who enabled notifications</span>
                            </label>
                        </fieldset>

                        <fieldset class="fieldset">
                            <label class="label" for="title">Title</label>
                            <input
                                id="title"
                                type="text"
                                name="title"
                                value="{{ old('title', 'Spice Rules') }}"
                                class="input w-full @error('title') input-error @enderror"
                                maxlength="80"
                                required
                            >
                        </fieldset>

                        <fieldset class="fieldset">
                            <label class="label" for="body">Message</label>
                            <input
                                id="body"
                                type="text"
                                name="body"
                                value="{{ old('body', 'This is a test ping.') }}"
                                class="input w-full @error('body') input-error @enderror"
                                maxlength="180"
                                required
                            >
                        </fieldset>

                        <button type="submit" class="btn btn-primary" @disabled(! $firebaseConfigured)>
                            Send test ping
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <input
            type="radio"
            name="admin_tabs"
            class="tab grow"
            aria-label="Overview"
            data-tab="overview"
            @checked($tab === 'overview')
        >
        <div class="tab-content mt-4">
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title">Overview</h2>
                    <p class="text-base-content/70">A quick look at who can receive a ping.</p>
                    <dl class="mt-2 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-box bg-base-200 p-4">
                            <dt class="text-sm text-base-content/60">Firebase</dt>
                            <dd class="mt-1 text-lg font-semibold">{{ $firebaseConfigured ? 'Ready' : 'Not configured' }}</dd>
                        </div>
                        <div class="rounded-box bg-base-200 p-4">
                            <dt class="text-sm text-base-content/60">People subscribed</dt>
                            <dd class="mt-1 text-lg font-semibold">{{ $subscriberCount }}</dd>
                        </div>
                        <div class="rounded-box bg-base-200 p-4">
                            <dt class="text-sm text-base-content/60">Registered devices</dt>
                            <dd class="mt-1 text-lg font-semibold">{{ $deviceCount }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
