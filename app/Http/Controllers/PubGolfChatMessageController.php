<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePubGolfChatMessageRequest;
use App\Models\PubGolfCrawl;
use App\Services\Chat\BuildChat;
use App\Services\Chat\SendChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PubGolfChatMessageController extends Controller
{
    public function index(
        Request $request,
        PubGolfCrawl $pubGolfCrawl,
        BuildChat $buildChat,
    ): JsonResponse {
        return response()->json($buildChat->handle($pubGolfCrawl, $request->user()));
    }

    public function store(
        StorePubGolfChatMessageRequest $request,
        PubGolfCrawl $pubGolfCrawl,
        SendChatMessage $sendChatMessage,
        BuildChat $buildChat,
    ): JsonResponse|RedirectResponse {
        $sendChatMessage->handle(
            $pubGolfCrawl,
            $request->user(),
            $request->messageBody(),
            $request->photo(),
        );

        if ($request->wantsJson()) {
            return response()->json($buildChat->handle($pubGolfCrawl, $request->user()));
        }

        return back();
    }
}
