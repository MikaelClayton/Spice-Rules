<?php

namespace App\Http\Controllers;

use App\Services\Spirdle\ControlSpirdlePlayTimer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpirdleResumeController extends Controller
{
    public function store(Request $request, ControlSpirdlePlayTimer $timer): JsonResponse
    {
        $play = $timer->resume($request->user());

        return response()->json([
            'ok' => true,
            'play' => $play?->gameState() ?? [],
        ]);
    }
}
