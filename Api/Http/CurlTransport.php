<?php

namespace Okay\Modules\Sviat\NovaPoshta\Api\Http;

final class CurlTransport implements HttpTransport
{
    private ?\CurlHandle $handle = null;

    public function send(HttpRequest $request): HttpResponse
    {
        // One handle per process keeps the TLS connection to Nova Poshta alive between calls.
        $this->handle ??= curl_init();
        curl_reset($this->handle);

        $headers = [];
        foreach ($request->headers as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }

        curl_setopt_array($this->handle, [
            CURLOPT_URL => $request->url,
            CURLOPT_CUSTOMREQUEST => $request->method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $request->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($request->body !== null) {
            curl_setopt($this->handle, CURLOPT_POSTFIELDS, $request->body);
        }

        $body = curl_exec($this->handle);
        if ($body === false) {
            // curl_error() text carries only the host, never the query string with the key.
            throw new TransportException(curl_error($this->handle) ?: 'Nova Poshta did not answer');
        }

        return new HttpResponse(curl_getinfo($this->handle, CURLINFO_RESPONSE_CODE), (string)$body);
    }
}
