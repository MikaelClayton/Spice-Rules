<section class="card bg-base-100 shadow-xl" data-day-overview="{{ $source }}">
    <div class="card-body gap-4 p-4">
        <div>
            <h2 class="card-title text-base">Round progress</h2>
            <p class="text-sm text-base-content/70">Running total after each round.</p>
            <div class="relative mt-2 h-44">
                <canvas data-day-chart="progress"></canvas>
            </div>
            <p class="hidden text-sm text-base-content/60" data-day-empty="progress">No round scores for this day yet.</p>
        </div>
        <div>
            <h2 class="card-title text-base">Each round</h2>
            <p class="text-sm text-base-content/70">Who scored what on rounds 1–5.</p>
            <div class="relative mt-2 h-44">
                <canvas data-day-chart="rounds"></canvas>
            </div>
            <p class="hidden text-sm text-base-content/60" data-day-empty="rounds">No round scores for this day yet.</p>
        </div>

        <ul class="hidden flex flex-col gap-1.5" data-day-legend></ul>
    </div>
</section>
