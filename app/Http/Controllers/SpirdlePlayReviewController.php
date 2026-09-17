<?php

namespace App\Http\Controllers;

use App\Models\SpirdlePlay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpirdlePlayReviewController extends Controller
{
    public function show(Request $request, SpirdlePlay $spirdlePlay): View|RedirectResponse
    {
        $spirdlePlay->load(['puzzle.word', 'user']);

        if (! $spirdlePlay->isFinished()) {
            abort(404);
        }

        $viewerFinished = SpirdlePlay::query()
            ->whereBelongsTo($request->user())
            ->where('spirdle_puzzle_id', $spirdlePlay->spirdle_puzzle_id)
            ->finished()
            ->exists();

        if (! $viewerFinished) {
            return redirect()->route('spirdle.index');
        }

        $date = $spirdlePlay->puzzle?->play_date;
        $tab = $request->string('tab')->toString();
        $challenge = $request->string('challenge')->toString() ?: ($date?->toDateString() ?? '');
        $fromChallenges = $tab === 'challenges' || ($date !== null && ! $date->isToday());
        $backUrl = $fromChallenges
            ? route('spirdle.index', array_filter([
                'tab' => 'challenges',
                'challenge' => $challenge,
            ]))
            : route('spirdle.index');

        return view('spirdle.play', [
            'puzzle' => $spirdlePlay->puzzle,
            'play' => $spirdlePlay,
            'game' => $spirdlePlay->gameState(),
            'readonly' => true,
            'playerName' => $spirdlePlay->user?->name,
            'backUrl' => $backUrl,
        ]);
    }
}
