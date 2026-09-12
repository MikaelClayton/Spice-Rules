<?php

namespace App\Http\Controllers;

use App\Models\WicketGroup;
use App\Services\Wickets\BuildWicketBoard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WicketGroupLiveController extends Controller
{
    public function __invoke(
        Request $request,
        WicketGroup $wicketGroup,
        BuildWicketBoard $buildWicketBoard,
    ): JsonResponse {
        $revision = $buildWicketBoard->revision($wicketGroup);

        if ($request->string('revision')->toString() === $revision) {
            return response()->json(['revision' => $revision]);
        }

        $board = $buildWicketBoard->handle($wicketGroup, $request->user());

        return response()->json([
            'revision' => $board['revision'],
            'regions' => [
                'owe' => view('wickets.owe', $board)->render(),
                'board' => view('wickets.board', $board)->render(),
                'drink' => view('wickets.drink', $board + ['group' => $wicketGroup])->render(),
            ],
        ]);
    }
}
