<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePubGolfChatReadRequest;
use App\Models\PubGolfCrawl;
use App\Services\Chat\BuildChat;
use App\Services\Chat\MarkChatRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class PubGolfChatReadController extends Controller
{
    public function store(
        StorePubGolfChatReadRequest $request,
        PubGolfCrawl $pubGolfCrawl,
        MarkChatRead $markChatRead,
        BuildChat $buildChat,
    ): JsonResponse|RedirectResponse {
        $markChatRead->handle($pubGolfCrawl, $request->user(), $request->lastReadMessageId());

        if ($request->wantsJson()) {
            return response()->json($buildChat->handle($pubGolfCrawl, $request->user()));
        }

        return back();
    }
}
