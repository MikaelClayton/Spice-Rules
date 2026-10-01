<?php

namespace App\Http\Controllers;

use App\Services\Spirdle\BuildSpirdlePracticeResults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpirdlePracticeResultsController extends Controller
{
    public function __invoke(Request $request, BuildSpirdlePracticeResults $results): JsonResponse
    {
        $board = $results->handle($request->user());

        return response()->json([
            'html' => view('spirdle.practice-results', $board)->render(),
        ]);
    }
}
