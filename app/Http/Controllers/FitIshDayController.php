<?php

namespace App\Http\Controllers;

use App\Services\FitIsh\BuildFitIshToday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FitIshDayController extends Controller
{
    public function __construct(private readonly BuildFitIshToday $today) {}

    public function __invoke(Request $request, string $date): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasFitIshProfile()) {
            abort(404);
        }

        $board = $this->today->handle($date);

        return response()->json([
            'date' => $board['date'],
            'html' => view('fit-ish.today', $board)->render(),
        ]);
    }
}
