<?php

namespace App\Http\Controllers;

use App\Services\Spirdle\EnsureTodaysSpirdlePuzzle;
use App\Services\Spirdle\StartSpirdlePlay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpirdlePlayController extends Controller
{
    public function __construct(
        private readonly EnsureTodaysSpirdlePuzzle $ensureTodaysSpirdlePuzzle,
        private readonly StartSpirdlePlay $startSpirdlePlay,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $puzzle = $this->ensureTodaysSpirdlePuzzle->handle();

        if ($puzzle === null) {
            return redirect()
                ->route('spirdle.index')
                ->with('status', 'Today\'s Spirdle is not ready yet.');
        }

        $play = $this->startSpirdlePlay->handle($request->user(), $puzzle);

        return view('spirdle.play', [
            'puzzle' => $puzzle,
            'play' => $play,
            'game' => $play->gameState(),
        ]);
    }
}
