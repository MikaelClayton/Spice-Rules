@extends('layouts.app')

@section('title', 'Dashboard — '.config('app.name'))

@section('content')
    <div class="mb-8">
        <h1 class="text-3xl font-bold">Today</h1>
        <p class="mt-1 text-base-content/70">Welcome, {{ Auth::user()->name }}. Pick a board to see how everyone did.</p>
    </div>

    <ul class="grid items-stretch gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($items as $item)
            <li class="flex h-full">
                @if ($item['available'])
                    <a href="{{ route($item['route']) }}" class="card flex h-full min-h-full w-full flex-col bg-base-100 shadow-xl transition hover:-translate-y-0.5 hover:shadow-2xl">
                        <div class="card-body flex flex-1 flex-col">
                            <h2 class="card-title">{{ $item['name'] }}</h2>
                            <p class="grow text-base-content/70">{{ $item['description'] }}</p>
                            <div class="card-actions mt-auto justify-end">
                                <span class="btn btn-primary">Open</span>
                            </div>
                        </div>
                    </a>
                @else
                    <div class="card flex h-full min-h-full w-full flex-col bg-base-100 shadow-xl opacity-70">
                        <div class="card-body flex flex-1 flex-col">
                            <div class="flex items-start justify-between gap-3">
                                <h2 class="card-title">{{ $item['name'] }}</h2>
                                <span class="badge badge-ghost">Soon</span>
                            </div>
                            <p class="grow text-base-content/70">{{ $item['description'] }}</p>
                            <div class="card-actions mt-auto justify-end">
                                <span class="btn btn-disabled">Coming soon</span>
                            </div>
                        </div>
                    </div>
                @endif
            </li>
        @endforeach
    </ul>
@endsection
