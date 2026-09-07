<?php

namespace App\Services\Wickets;

use App\Models\User;
use App\Models\WicketGroup;
use App\Models\WicketSipLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordWicketSips
{
    public function __construct(private ApplyOutstandingSips $applyOutstandingSips) {}

    public function handle(WicketGroup $group, User $user, int $sips): WicketSipLog
    {
        return DB::transaction(function () use ($group, $user, $sips): WicketSipLog {
            $applied = $this->applyOutstandingSips->handle($group, $user, $sips);

            if ($applied === 0) {
                throw ValidationException::withMessages([
                    'sips' => 'You have no sip fines left to drink.',
                ]);
            }

            return WicketSipLog::query()->create([
                'wicket_group_id' => $group->id,
                'user_id' => $user->id,
                'sips' => $applied,
            ]);
        });
    }
}
