<?php

namespace Okay\Modules\Sviat\NovaPoshta\Api\Http;

final readonly class HttpResponse
{
    public function __construct(
        public int $status,
        public string $body,
    ) {
    }

    /** Decoded JSON object or array; null when the body is not JSON of that shape. */
    public function json(): ?array
    {
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : null;
    }
}
