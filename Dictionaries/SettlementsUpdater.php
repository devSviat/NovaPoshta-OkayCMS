<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries;

use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Rows\SettlementRow;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Store\DictionaryStore;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Stream\JsonItemStream;

/** Settlements come as a whole base only (no deltas on the CDN). */
final class SettlementsUpdater
{
    public function __construct(private readonly DictionaryStore $store)
    {
    }

    public function full(string $ukFile, ?string $enFile, int $run): UpdateCounts
    {
        $english = [];
        if ($enFile !== null) {
            foreach (JsonItemStream::items($enFile) as $item) {
                $english[(int)$item['id']] = ['name' => $item['name'] ?? null, 'region' => $item['region'] ?? null];
            }
        }

        $loader = new TableLoader($this->store, DictionaryStore::SETTLEMENTS, $run, touchUnchanged: true);
        foreach (JsonItemStream::items($ukFile) as $item) {
            if (isset($item['id'])) {
                $loader->put(SettlementRow::fromNp($item, $english[$item['id']] ?? null));
            }
        }
        $counts = $loader->finish();

        return new UpdateCounts($counts->added, $counts->changed, $this->store->deleteNotSeen(DictionaryStore::SETTLEMENTS, $run), $counts->unchanged);
    }
}
