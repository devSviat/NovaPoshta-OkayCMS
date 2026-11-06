<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries\Cdn;

/** What the CDN offers for one dictionary and language: a full base and daily deltas. */
final readonly class CdnVersions
{
    /** @param list<array{from:int, till:int, url:string}> $deltas oldest first */
    public function __construct(
        public int $baseTime,
        public string $baseUrl,
        public array $deltas,
    ) {
    }

    /** `{"base_version":{"unix_time","url"},"deltas":[{"unix_time_from","unix_time_till","url"}]}` */
    public static function fromDivisions(array $json): ?self
    {
        $base = $json['base_version'] ?? null;
        if (!is_int($base['unix_time'] ?? null) || !is_string($base['url'] ?? null) || !is_array($json['deltas'] ?? null)) {
            return null;
        }
        $deltas = [];
        foreach ($json['deltas'] as $delta) {
            if (!is_int($delta['unix_time_from'] ?? null) || !is_int($delta['unix_time_till'] ?? null) || !is_string($delta['url'] ?? null)) {
                return null;
            }
            $deltas[] = ['from' => $delta['unix_time_from'], 'till' => $delta['unix_time_till'], 'url' => $delta['url']];
        }
        usort($deltas, static fn(array $a, array $b): int => $a['from'] <=> $b['from']);

        return new self($base['unix_time'], $base['url'], $deltas);
    }

    /** `{"unix_time","base_version":{"unix_time"},"urls":[base]}`: settlements come as a base only. */
    public static function fromSettlements(array $json): ?self
    {
        $time = $json['base_version']['unix_time'] ?? null;
        $url = $json['urls'][0] ?? null;

        return is_int($time) && is_string($url) ? new self($time, $url, []) : null;
    }
}
