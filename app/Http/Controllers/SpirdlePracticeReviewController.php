<?php

namespace App\Http\Controllers;

use App\Models\SpirdlePractice;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpirdlePracticeReviewController extends Controller
{
    public function show(Request $request, SpirdlePractice $spirdlePractice): View
    {
        abort_unless($spirdlePractice->user_id === $request->user()->id, 404);
        abort_unless($spirdlePractice->isFinished(), 404);

        $spirdlePractice->loadMissing('word');

        return view('spirdle.play', [
            'mode' => 'practice',
            'practice' => $spirdlePractice,
            'game' => $spirdlePractice->gameState(),
            'readonly' => true,
            'playerName' => $spirdlePractice->roundSolution()
                ? strtoupper($spirdlePractice->roundSolution())
                : 'Practice',
            'backUrl' => route('spirdle.index', ['tab' => 'you']),
        ]);
    }
}
