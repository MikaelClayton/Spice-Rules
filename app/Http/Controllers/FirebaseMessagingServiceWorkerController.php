<?php

namespace App\Http\Controllers;

use App\Services\Push\FirebaseConfig;
use Illuminate\Http\Response;
use JsonException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FirebaseMessagingServiceWorkerController extends Controller
{
    public function __invoke(FirebaseConfig $firebase): Response
    {
        if (! $firebase->isClientConfigured()) {
            throw new NotFoundHttpException;
        }

        try {
            $config = json_encode($firebase->webConfig(), JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new NotFoundHttpException(previous: $exception);
        }

        $version = FirebaseConfig::SDK_VERSION;
        $script = <<<JS
importScripts('https://www.gstatic.com/firebasejs/{$version}/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/{$version}/firebase-messaging-compat.js');
firebase.initializeApp({$config});
firebase.messaging();
JS;

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
