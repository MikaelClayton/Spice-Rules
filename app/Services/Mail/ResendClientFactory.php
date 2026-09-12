<?php

namespace App\Services\Mail;

use App\Services\Http\LogOutgoingGuzzleMiddleware;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use Illuminate\Contracts\Foundation\Application;
use Resend\Client;
use Resend\Transporters\HttpTransporter;
use Resend\ValueObjects\ApiKey;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;

class ResendClientFactory
{
    public function __construct(
        private readonly Application $app,
        private readonly LogOutgoingGuzzleMiddleware $middleware,
    ) {}

    public function make(?string $apiKey = null): Client
    {
        $apiKey = ApiKey::from($apiKey ?? (string) $this->app['config']->get('services.resend.key'));
        $baseUri = BaseUri::from((string) $this->app['config']->get('services.resend.base_url', 'api.resend.com'));
        $headers = Headers::withAuthorization($apiKey);

        $handler = $this->app->bound('resend.guzzle.handler')
            ? $this->app->make('resend.guzzle.handler')
            : null;

        $stack = HandlerStack::create($handler);
        $stack->push($this->middleware);

        return new Client(new HttpTransporter(
            new GuzzleClient(['handler' => $stack]),
            $baseUri,
            $headers,
        ));
    }
}
