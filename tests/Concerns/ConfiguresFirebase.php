<?php

namespace Tests\Concerns;

trait ConfiguresFirebase
{
    private function enableFirebase(): void
    {
        config([
            'services.firebase.api_key' => 'test-api-key',
            'services.firebase.auth_domain' => 'spice-rules-test.firebaseapp.com',
            'services.firebase.project_id' => 'spice-rules-test',
            'services.firebase.storage_bucket' => 'spice-rules-test.appspot.com',
            'services.firebase.messaging_sender_id' => '123456789',
            'services.firebase.app_id' => '1:123456789:web:abcdef',
            'services.firebase.vapid_key' => 'test-vapid-key',
            'services.firebase.client_email' => 'firebase-adminsdk@spice-rules-test.iam.gserviceaccount.com',
            'services.firebase.private_key' => (string) file_get_contents(base_path('tests/Fixtures/firebase-test-key.pem')),
        ]);
    }
}
