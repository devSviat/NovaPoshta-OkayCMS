<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries;

use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Rows\SettlementRow;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Store\DictionaryStore;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Stream\JsonItemStream;

/** Населені пункти приходять лише повною базою (дельт на CDN немає). */
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
        $loader->assertPlausibleFullFile();

        $deleted = $this->store->deleteNotSeen(DictionaryStore::SETTLEMENTS, $run);
        $this->store->refreshPickup();

        return new UpdateCounts($counts->added, $counts->changed, $deleted, $counts->unchanged);
    }
}
