<?php

namespace App\Services\Http;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create as PromiseCreate;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class LogOutgoingGuzzleMiddleware
{
    public function __construct(private readonly LogOutgoingApiCall $logger) {}

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($request) {
                    $this->logger->fromPsr($request, $response);

                    return $response;
                },
                function (mixed $reason) use ($request) {
                    $response = $reason instanceof RequestException ? $reason->getResponse() : null;
                    $error = $reason instanceof Throwable ? $reason->getMessage() : (string) $reason;

                    $this->logger->fromPsr($request, $response, $error);

                    return PromiseCreate::rejectionFor($reason);
                },
            );
        };
    }
}
