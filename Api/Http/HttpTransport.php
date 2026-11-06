<?php

namespace Okay\Modules\Sviat\NovaPoshta\Api\Http;

interface HttpTransport
{
    /**
     * Any HTTP status, error statuses included, comes back as a response.
     *
     * @throws TransportException when no response arrived (network, DNS, TLS, timeout)
     */
    public function send(HttpRequest $request): HttpResponse;
}
