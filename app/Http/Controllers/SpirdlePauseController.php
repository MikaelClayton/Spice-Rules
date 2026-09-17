<?php

namespace App\Http\Controllers;

use App\Services\Spirdle\ControlSpirdlePlayTimer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpirdlePauseController extends Controller
{
    public function store(Request $request, ControlSpirdlePlayTimer $timer): JsonResponse
    {
        $play = $timer->pause($request->user());

        return response()->json([
            'ok' => true,
            'play' => $play?->gameState() ?? [],
        ]);
    }
}
