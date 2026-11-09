<?php

namespace Okay\Modules\Sviat\NovaPoshta\Api\Http;

final readonly class HttpResponse
{
    public function __construct(
        public int $status,
        public string $body,
    ) {
    }

    /** Декодований JSON-об'єкт або масив; null, коли тіло не є JSON такої форми. */
    public function json(): ?array
    {
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : null;
    }
}
