@if ($pins !== [])
    <section class="card mb-5 bg-base-100 shadow-xl">
        <div class="card-body p-4 sm:p-5">
            <h2 class="card-title">{{ $heading }}</h2>
            @if ($intro !== '')
                <p class="mt-1 text-sm text-base-content/70">{{ $intro }}</p>
            @endif
            <div class="relative mt-2 h-80 overflow-hidden rounded-xl" data-pub-golf-map-wrap>
                <div class="h-full w-full" data-pub-golf-map></div>
                <button type="button" class="btn btn-neutral btn-sm absolute right-3 top-3 z-[1100]" data-map-fullscreen>Full screen</button>
                <script type="application/json" data-pub-golf-map-pins>@json($pins)</script>
            </div>
        </div>
    </section>
@endif
