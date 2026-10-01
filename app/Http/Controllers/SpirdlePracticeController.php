<?php

namespace App\Http\Controllers;

use App\Services\Spirdle\StartSpirdlePractice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpirdlePracticeController extends Controller
{
    public function show(Request $request, StartSpirdlePractice $startSpirdlePractice): View|RedirectResponse
    {
        $practice = $startSpirdlePractice->handle($request->user());

        if ($practice === null) {
            return redirect()
                ->route('spirdle.index')
                ->with('status', 'Practice needs another word. Try again after today\'s Spirdle is open.');
        }

        return view('spirdle.play', [
            'mode' => 'practice',
            'practice' => $practice,
            'game' => $practice->gameState(),
        ]);
    }
}
