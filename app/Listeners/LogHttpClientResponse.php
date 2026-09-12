<?php

namespace App\Listeners;

use App\Services\Http\LogOutgoingApiCall;
use Illuminate\Http\Client\Events\ResponseReceived;

class LogHttpClientResponse
{
    public function __construct(private readonly LogOutgoingApiCall $logger) {}

    public function handle(ResponseReceived $event): void
    {
        $this->logger->fromLaravel($event->request, $event->response);
    }
}
