<?php

namespace Okay\Modules\Sviat\NovaPoshta\Backend;

use Okay\Modules\Sviat\NovaPoshta\Api\ApiKey;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyAccess;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyCheckResult;

/** Template data for the key block; the key itself only ever leaves here masked. */
final class KeySettingsView
{
    public static function vars(
        ?string $savedKey,
        ?KeyCheckResult $lastResult,
        ?KeySubmission $submission,
        ?\DateTimeZone $timezone = null,
    ): array {
        $result = $submission?->result ?? $lastResult;
        $rejected = $submission !== null && !$submission->saved && !$submission->recheck;

        return [
            'has_key' => $savedKey !== null,
            'key_masked' => $savedKey === null ? null : ApiKey::masked($savedKey),
            'state' => self::state($savedKey, $result),
            'rows' => $result === null ? [] : [
                ['api' => 'new_api', 'access' => $result->newApi->value],
                ['api' => 'old_api', 'access' => $result->oldApi->value],
            ],
            'checked_at' => $result?->checkedAt
                ->setTimezone($timezone ?? new \DateTimeZone(date_default_timezone_get()))
                ->format('d.m.Y H:i'),
            'just_saved' => $submission !== null && $submission->saved,
            'rejected' => $rejected,
            'kept_previous' => $rejected && $savedKey !== null,
        ];
    }

    private static function state(?string $savedKey, ?KeyCheckResult $result): string
    {
        if ($result === null) {
            return $savedKey === null ? 'none' : 'unchecked';
        }
        $access = [$result->newApi, $result->oldApi];

        // The worst finding decides: an invalid key tells more than an unanswered request.
        return match (true) {
            in_array(KeyAccess::InvalidKey, $access, true) => 'invalid',
            in_array(KeyAccess::Denied, $access, true) => 'denied',
            in_array(KeyAccess::Unreachable, $access, true) => 'unreachable',
            default => 'ok',
        };
    }
}
