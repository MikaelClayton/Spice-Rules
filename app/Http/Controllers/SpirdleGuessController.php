<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSpirdleGuessRequest;
use App\Services\Spirdle\SubmitSpirdleGuess;
use Illuminate\Http\JsonResponse;

class SpirdleGuessController extends Controller
{
    public function store(StoreSpirdleGuessRequest $request, SubmitSpirdleGuess $submitSpirdleGuess): JsonResponse
    {
        $result = $submitSpirdleGuess->handle($request->user(), $request->word());

        $status = $result['status'];

        if ($status === 'missing') {
            return response()->json($result, 409);
        }

        return response()->json($result);
    }
}
