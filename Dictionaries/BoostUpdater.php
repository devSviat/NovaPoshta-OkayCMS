<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries;

use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\NewApiClient;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Store\DictionaryStore;

/**
 * Популярність населених пунктів (`boost`), доступна лише з API, а не з файлів CDN.
 * API віддає населені пункти за спаданням boost, і ненульовий boost закінчується в перших
 * ~15 із 276 сторінок (виміряно 06.11.2026), тож читання зупиняється на першій сторінці без нього.
 */
final class BoostUpdater
{
    /** Захист від списку, у якому boost ніколи не закінчується. */
    public const int MAX_PAGES = 40;

    public function __construct(
        private readonly NewApiClient $api,
        private readonly DictionaryStore $store,
    ) {
    }

    /** @return int населені пункти з ненульовим boost; нічого не записується, доки не прочитано всі сторінки */
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
                // Список відсортовано за boost: його відсутність на сторінці 1 означає, що API змінився, а не що жодне місто не популярне.
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
