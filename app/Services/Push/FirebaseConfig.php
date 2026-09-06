<?php

namespace App\Services\Push;

class FirebaseConfig
{
    public const SDK_VERSION = '11.10.0';

    public function isClientConfigured(): bool
    {
        return filled(config('services.firebase.api_key'))
            && filled(config('services.firebase.project_id'))
            && filled(config('services.firebase.app_id'))
            && filled(config('services.firebase.messaging_sender_id'))
            && filled(config('services.firebase.vapid_key'));
    }

    public function isServerConfigured(): bool
    {
        return $this->isClientConfigured()
            && filled(config('services.firebase.client_email'))
            && filled(config('services.firebase.private_key'));
    }

    /**
     * @return array{
     *     apiKey: string,
     *     authDomain: string,
     *     projectId: string,
     *     storageBucket: string,
     *     messagingSenderId: string,
     *     appId: string
     * }
     */
    public function webConfig(): array
    {
        $projectId = (string) config('services.firebase.project_id');

        return [
            'apiKey' => (string) config('services.firebase.api_key'),
            'authDomain' => (string) (config('services.firebase.auth_domain') ?: $projectId.'.firebaseapp.com'),
            'projectId' => $projectId,
            'storageBucket' => (string) (config('services.firebase.storage_bucket') ?: $projectId.'.appspot.com'),
            'messagingSenderId' => (string) config('services.firebase.messaging_sender_id'),
            'appId' => (string) config('services.firebase.app_id'),
        ];
    }
}
