<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSpirdleGuessRequest;
use App\Services\Spirdle\SubmitSpirdlePracticeGuess;
use Illuminate\Http\JsonResponse;

class SpirdlePracticeGuessController extends Controller
{
    public function store(StoreSpirdleGuessRequest $request, SubmitSpirdlePracticeGuess $submitSpirdlePracticeGuess): JsonResponse
    {
        $result = $submitSpirdlePracticeGuess->handle($request->user(), $request->word());

        if ($result['status'] === 'missing') {
            return response()->json($result, 409);
        }

        return response()->json($result);
    }
}
