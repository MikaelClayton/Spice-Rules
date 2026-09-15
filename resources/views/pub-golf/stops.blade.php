@if ($stops !== [])
    <section class="card bg-base-100 shadow-xl">
        <div class="card-body gap-3 p-4 sm:p-5">
            <div>
                <h2 class="card-title">{{ $heading }}</h2>
                @if ($intro !== '')
                    <p class="mt-1 text-sm text-base-content/70">{{ $intro }}</p>
                @endif
            </div>
            <ol class="relative ml-3 flex flex-col gap-4 border-l border-base-300">
                @foreach ($stops as $index => $stop)
                    <li class="relative pl-5">
                        <span class="absolute -left-2 top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-[0.65rem] font-bold leading-none text-primary-content">{{ $index + 1 }}</span>
                        <p class="font-medium leading-tight">{{ $stop['location'] }}</p>
                        <p class="mt-0.5 text-sm text-base-content/60">
                            {{ $stop['drink_label'] }}
                            <span class="text-base-content/30">·</span>
                            {{ $stop['when'] }}
                        </p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
@endif
