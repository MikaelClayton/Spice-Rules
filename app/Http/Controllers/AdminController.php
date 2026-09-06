<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendTestPushNotificationRequest;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Push\FcmClient;
use App\Services\Push\FirebaseConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(FirebaseConfig $firebase): View
    {
        abort_unless(request()->user()?->isAdmin() === true, 403);

        $user = request()->user();

        return view('admin.index', [
            'firebaseConfigured' => $firebase->isServerConfigured(),
            'myDeviceCount' => $user->deviceTokens()->count(),
            'deviceCount' => DeviceToken::query()->count(),
            'subscriberCount' => User::query()->whereHas('deviceTokens')->count(),
            'activeTab' => request()->string('tab')->toString() === 'overview' ? 'overview' : 'push',
        ]);
    }

    public function sendPush(SendTestPushNotificationRequest $request, FcmClient $fcm, FirebaseConfig $firebase): RedirectResponse
    {
        $redirect = redirect()->route('admin.index', ['tab' => 'push']);

        if (! $firebase->isServerConfigured()) {
            return $redirect->withErrors(['push' => 'Firebase is not configured, so nothing can be sent.']);
        }

        $tokens = $request->string('audience')->toString() === 'everyone'
            ? DeviceToken::query()->orderBy('id')->pluck('token')
            : $request->user()->deviceTokens()->orderBy('id')->pluck('token');

        if ($tokens->isEmpty()) {
            return $redirect->withErrors([
                'push' => $request->string('audience')->toString() === 'everyone'
                    ? 'Nobody has enabled notifications yet.'
                    : 'Enable notifications on your Profile first, then send a test ping.',
            ]);
        }

        $result = $fcm->sendToTokens($tokens->all(), [
            'title' => $request->validated('title'),
            'body' => $request->validated('body'),
            'url' => route('geoguessr.index'),
        ]);

        if ($result['sent'] === 0) {
            return $redirect->withErrors(['push' => 'The test ping did not reach any device.']);
        }

        $status = $result['sent'] === 1
            ? 'Test ping sent to 1 device.'
            : 'Test ping sent to '.$result['sent'].' devices.';

        if ($result['failed'] > 0) {
            $status .= $result['failed'] === 1
                ? ' 1 token failed.'
                : ' '.$result['failed'].' tokens failed.';
        }

        return $redirect->with('status', $status);
    }
}
