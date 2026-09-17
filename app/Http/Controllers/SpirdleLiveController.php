<?php

namespace App\Http\Controllers;

use App\Services\Spirdle\BuildSpirdleToday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpirdleLiveController extends Controller
{
    public function __construct(private readonly BuildSpirdleToday $buildSpirdleToday) {}

    public function __invoke(Request $request): JsonResponse
    {
        $revision = $this->buildSpirdleToday->revision();

        if ($request->string('revision')->toString() === $revision) {
            return response()->json(['revision' => $revision]);
        }

        $today = $this->buildSpirdleToday->handle($request->user());

        return response()->json([
            'revision' => $today['revision'],
            'regions' => [
                'today' => view('spirdle.today', $today)->render(),
            ],
        ]);
    }
}
