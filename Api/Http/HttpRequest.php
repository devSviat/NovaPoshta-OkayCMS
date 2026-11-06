<?php

namespace Okay\Modules\Sviat\NovaPoshta\Api\Http;

final readonly class HttpRequest
{
    /** @param array<string, string> $headers */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers = [],
        public ?string $body = null,
        public int $timeoutSeconds = 20,
    ) {
    }
}
