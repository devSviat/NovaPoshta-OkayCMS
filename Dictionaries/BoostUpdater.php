<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries;

use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\NewApiClient;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Store\DictionaryStore;

/**
 * Popularity of settlements (`boost`), available only from the API, not the CDN files.
 * The API lists settlements by boost descending, and non-zero boost ends within the first
 * ~15 of 276 pages (measured 06.11.2026), so reading stops at the first page without it.
 */
final class BoostUpdater
{
    /** Guard against a list that never runs out of boost. */
    public const int MAX_PAGES = 40;

    public function __construct(
        private readonly NewApiClient $api,
        private readonly DictionaryStore $store,
    ) {
    }

    /** @return int settlements with non-zero boost; nothing is written unless every page was read */
    public function update(): int
    {
        $boostById = [];
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $answer = $this->api->get('/settlements', ['countryCodes' => ['UA'], 'limit' => 100, 'page' => $page]);
            $foundOnPage = false;
            foreach ((array)($answer['items'] ?? []) as $item) {
                $boost = (int)($item['boost'] ?? 0);
                if ($boost > 0 && isset($item['id'])) {
                    $boostById[(int)$item['id']] = $boost;
                    $foundOnPage = true;
                }
            }
            if (!$foundOnPage && $page === 1) {
                // The list is sorted by boost: none on page 1 means the API changed, not that no city is popular.
                throw NpApiException::fromNp(NpErrorKind::Malformed, ['No settlement popularity on the first page']);
            }
            if (!$foundOnPage || $page >= (int)($answer['last_page'] ?? $page)) {
                break;
            }
        }

        $this->store->setBoost($boostById);

        return count($boostById);
    }
}
