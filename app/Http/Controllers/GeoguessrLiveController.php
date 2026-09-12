<?php

namespace App\Http\Controllers;

use App\Services\Geoguessr\BuildGeoguessrToday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeoguessrLiveController extends Controller
{
    public function __construct(private readonly BuildGeoguessrToday $buildGeoguessrToday) {}

    public function __invoke(Request $request): JsonResponse
    {
        $revision = $this->buildGeoguessrToday->revision();

        if ($request->string('revision')->toString() === $revision) {
            return response()->json(['revision' => $revision]);
        }

        $today = $this->buildGeoguessrToday->handle();

        return response()->json([
            'revision' => $today['revision'],
            'regions' => [
                'today' => view('geoguessr.today', $today)->render(),
            ],
        ]);
    }
}
