<?php

namespace App\Http\Controllers;

use App\Services\Spirdle\BuildSpirdleChallenges;
use App\Services\Spirdle\BuildSpirdleToday;
use App\Services\Spirdle\BuildSpirdleWeekly;
use App\Services\Spirdle\BuildSpirdleYou;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpirdleController extends Controller
{
    public function __construct(
        private readonly BuildSpirdleToday $today,
        private readonly BuildSpirdleWeekly $weekly,
        private readonly BuildSpirdleYou $you,
        private readonly BuildSpirdleChallenges $challenges,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('spirdle.index', [
            ...$this->today->handle($user),
            'activeTab' => $this->activeTab(),
            'weekly' => $this->weekly->payload($request->string('week')->toString() ?: null),
            'you' => $this->you->handle($user),
            'dailies' => $this->challenges->handle($user),
        ]);
    }

    private function activeTab(): string
    {
        $tab = request()->string('tab')->toString();

        return in_array($tab, ['weekly', 'you', 'challenges'], true) ? $tab : 'today';
    }
}
