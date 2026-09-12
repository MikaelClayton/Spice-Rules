<?php

namespace App\Services\PubGolf;

use App\Models\PubGolfCrawl;
use App\Models\PubGolfParticipant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EndStalePubGolfCrawls
{
    public function handle(): int
    {
        $cutoff = now()->subHours((int) config('pub-golf.stale_after_hours'));
        $ended = 0;

        PubGolfCrawl::query()
            ->open()
            ->where('started_at', '<=', $cutoff)
            ->whereDoesntHave(
                'drinkLogs',
                fn (Builder $query) => $query->where('created_at', '>', $cutoff),
            )
            ->whereDoesntHave(
                'participants',
                function (Builder $query) use ($cutoff): void {
                    $query->where('joined_at', '>', $cutoff)
                        ->orWhere('left_at', '>', $cutoff);
                },
            )
            ->orderBy('id')
            ->eachById(function (PubGolfCrawl $crawl) use (&$ended): void {
                if ($this->end($crawl)) {
                    $ended++;
                }
            });

        return $ended;
    }

    private function end(PubGolfCrawl $crawl): bool
    {
        return DB::transaction(function () use ($crawl): bool {
            $locked = PubGolfCrawl::query()
                ->whereKey($crawl->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || ! $locked->isOpen()) {
                return false;
            }

            $endedAt = now();

            PubGolfParticipant::query()
                ->whereBelongsTo($locked, 'crawl')
                ->active()
                ->update(['left_at' => $endedAt]);

            $locked->update(['ended_at' => $endedAt]);

            return true;
        });
    }
}
