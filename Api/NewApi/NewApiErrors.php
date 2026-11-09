<?php

namespace Okay\Modules\Sviat\NovaPoshta\Api\NewApi;

use Okay\Modules\Sviat\NovaPoshta\Api\Http\HttpResponse;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;

/**
 * Новий API повідомляє про помилки як HTTP-статус + {"errors": {field: text}}, без кодів
 * (виміряно 06.11.2026: auth 422 "API key expired", data 401 "Invalid token").
 */
final class NewApiErrors
{
    /** @param list<?string> $secrets */
    public static function fromResponse(HttpResponse $response, array $secrets, bool $isAuthorization = false): NpApiException
    {
        $body = $response->json();
        $texts = [];
        foreach ((array)($body['errors'] ?? []) as $field => $text) {
            $text = is_scalar($text) ? (string)$text : (string)json_encode($text, JSON_UNESCAPED_UNICODE);
            $texts[] = $field === 'errorMessage' || is_int($field) ? $text : "{$field}: {$text}";
        }

        $kind = match (true) {
            $response->status >= 500 => NpErrorKind::Unavailable,
            $response->status === 429 => NpErrorKind::RateLimited,
            $body === null => NpErrorKind::Malformed,
            $isAuthorization && in_array($response->status, [400, 422], true) => NpErrorKind::InvalidKey,
            $response->status === 401, $response->status === 403 => NpErrorKind::Unauthorized,
            default => NpErrorKind::Rejected,
        };

        return NpApiException::fromNp($kind, $texts ?: ["HTTP {$response->status}"], [], $secrets);
    }
}
