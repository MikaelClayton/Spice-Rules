<?php

namespace App\Listeners;

use App\Services\Http\LogOutgoingApiCall;
use Illuminate\Http\Client\Events\ConnectionFailed;

class LogHttpClientConnectionFailed
{
    public function __construct(private readonly LogOutgoingApiCall $logger) {}

    public function handle(ConnectionFailed $event): void
    {
        $this->logger->fromLaravel($event->request, null, $event->exception->getMessage());
    }
}
