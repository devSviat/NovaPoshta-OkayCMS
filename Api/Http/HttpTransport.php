<?php

namespace Okay\Modules\Sviat\NovaPoshta\Api\Http;

interface HttpTransport
{
    /**
     * Будь-який HTTP-статус, зокрема статуси помилок, повертається як відповідь.
     *
     * @throws TransportException коли відповідь не надійшла (мережа, DNS, TLS, тайм-аут)
     */
    public function send(HttpRequest $request): HttpResponse;
}
