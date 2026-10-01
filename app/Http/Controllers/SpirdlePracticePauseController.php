<?php

namespace App\Http\Controllers;

use App\Services\Spirdle\ControlSpirdlePracticeTimer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpirdlePracticePauseController extends Controller
{
    public function store(Request $request, ControlSpirdlePracticeTimer $timer): JsonResponse
    {
        $practice = $timer->pause($request->user());

        return response()->json([
            'ok' => true,
            'play' => $practice?->gameState() ?? [],
        ]);
    }
}
