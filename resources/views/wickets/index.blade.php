@extends('layouts.app')

@section('title', 'Wickets — '.config('app.name'))

@section('content')
    <div class="mb-4">
        <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm -ml-2">← Back</a>
    </div>

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold sm:text-3xl">Wickets</h1>
            <p class="mt-1 text-base-content/70">Set up a group, hand out fines, drink them down.</p>
        </div>
        <a href="{{ route('wickets.create') }}" class="btn btn-primary w-full sm:w-auto">New group</a>
    </div>

    @if (session('status'))
        <div role="alert" class="alert alert-success mb-5">
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($groups->isEmpty())
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title">No groups yet</h2>
                <p class="text-base-content/70">Create a group for the event, add people, then start fining.</p>
                <div class="card-actions">
                    <a href="{{ route('wickets.create') }}" class="btn btn-primary w-full sm:w-auto">Create a group</a>
                </div>
            </div>
        </div>
    @else
        <ul class="grid gap-3">
            @foreach ($groups as $group)
                <li>
                    <a href="{{ route('wickets.show', $group) }}" class="card bg-base-100 shadow-md transition hover:-translate-y-0.5 hover:shadow-xl">
                        <div class="card-body p-4 sm:p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h2 class="card-title text-lg">{{ $group->name }}</h2>
                                    <p class="mt-1 text-sm text-base-content/70">
                                        {{ $group->users_count }} {{ $group->users_count === 1 ? 'player' : 'players' }}
                                        @if ($group->isTournament())
                                            · Tournament
                                        @endif
                                    </p>
                                </div>
                                @if ($group->isTournament())
                                    <span class="badge badge-secondary shrink-0">Tournament</span>
                                @elseif ($group->outstanding_fines_count > 0)
                                    <span class="badge badge-primary shrink-0">{{ $group->outstanding_fines_count }} open</span>
                                @else
                                    <span class="badge badge-ghost shrink-0">Clear</span>
                                @endif
                            </div>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
