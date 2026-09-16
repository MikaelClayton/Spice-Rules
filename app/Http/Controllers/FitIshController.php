<?php

namespace App\Http\Controllers;

use App\Models\FitIshProfileSummary;
use App\Models\FitIshSession;
use App\Services\FitIsh\BuildFitIshSessions;
use App\Services\FitIsh\BuildFitIshToday;
use App\Services\FitIsh\BuildFitIshWeekly;
use App\Services\FitIsh\BuildFitIshWorkouts;
use App\Services\FitIsh\BuildFitIshYou;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FitIshController extends Controller
{
    public function __construct(
        private readonly BuildFitIshToday $today,
        private readonly BuildFitIshWeekly $weekly,
        private readonly BuildFitIshSessions $sessions,
        private readonly BuildFitIshWorkouts $workouts,
        private readonly BuildFitIshYou $you,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasFitIshProfile()) {
            return redirect()->route('profile.edit', ['tab' => 'fit-ish']);
        }

        $summaries = FitIshProfileSummary::query()
            ->whereBelongsTo($user)
            ->orderBy('timeframe_id')
            ->orderBy('id')
            ->get()
            ->keyBy('timeframe_key');

        $activeTab = $this->activeTab();
        $recent = FitIshSession::query()
            ->with(['studio', 'workout'])
            ->whereBelongsTo($user)
            ->orderByDesc('class_date')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $sessionDates = $this->sessions->dates();
        $selectedSessionDate = $this->selectedSessionDate($sessionDates);

        return view('fit-ish.index', [
            ...$this->today->handle(),
            'activeTab' => $activeTab,
            'weekly' => $this->weekly->payload($request->string('week')->toString() ?: null),
            'summaries' => $summaries,
            'recent' => $recent,
            'sessionDates' => $sessionDates,
            'selectedSessionDate' => $selectedSessionDate,
            'sessionDay' => $activeTab === 'sessions' && $selectedSessionDate !== null
                ? $this->today->handle($selectedSessionDate)
                : null,
            'workouts' => $this->workouts->handle(),
            'you' => $this->you->handle($user),
        ]);
    }

    private function activeTab(): string
    {
        $tab = request()->string('tab')->toString();

        return in_array($tab, ['weekly', 'sessions', 'workouts', 'you'], true) ? $tab : 'today';
    }

    /**
     * @param  list<array{date: string, label: string}>  $dates
     */
    private function selectedSessionDate(array $dates): ?string
    {
        $keys = array_column($dates, 'date');
        $requested = request()->string('date')->toString();

        if (in_array($requested, $keys, true)) {
            return $requested;
        }

        return $keys[0] ?? null;
    }
}
