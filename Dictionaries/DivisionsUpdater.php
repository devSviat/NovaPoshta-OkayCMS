<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries;

use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Rows\DivisionRow;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Store\DictionaryStore;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Stream\JsonItemStream;

final class DivisionsUpdater
{
    /** Файли пунктів на CDN містять усі країни: декодується лише Україна. */
    private const string UKRAINE = '"countryCode":"UA"';

    public function __construct(private readonly DictionaryStore $store)
    {
    }

    /** Повна база: рядки, яких у ній немає, видаляються, але лише після того, як файл прочитано до кінця. */
    public function full(string $ukFile, ?string $enFile, int $run): UpdateCounts
    {
        $english = $this->english($enFile);
        $loader = new TableLoader($this->store, DictionaryStore::DIVISIONS, $run, touchUnchanged: true);
        foreach ($this->ukrainian($ukFile) as $item) {
            if (self::open($item)) {
                $loader->put(DivisionRow::fromNp($item, $english[$item['id']] ?? null));
            }
        }
        $counts = $loader->finish();
        $loader->assertPlausibleFullFile();

        $deleted = $this->store->deleteNotSeen(DictionaryStore::DIVISIONS, $run);
        $this->store->refreshPickup();

        return new UpdateCounts($counts->added, $counts->changed, $deleted, $counts->unchanged);
    }

    /** Щоденна дельта: те, чого не було б у базі (видалене, закрите, недоступне покупцям), прибирається, решта записується. */
    public function delta(string $ukFile, ?string $enFile, int $run): UpdateCounts
    {
        $english = $this->english($enFile);
        $loader = new TableLoader($this->store, DictionaryStore::DIVISIONS, $run, touchUnchanged: false);
        $deletedIds = [];
        foreach ($this->ukrainian($ukFile) as $item) {
            if (!empty($item['deletedAt']) || !self::open($item)) {
                if ($loader->isKnown((int)$item['id'])) {
                    $deletedIds[] = (int)$item['id'];
                }
                continue;
            }
            $loader->put(DivisionRow::fromNp($item, $english[$item['id']] ?? null));
        }
        $counts = $loader->finish();
        $deleted = $deletedIds === [] ? 0 : $this->store->delete(DictionaryStore::DIVISIONS, $deletedIds);
        $this->store->refreshPickup();

        return new UpdateCounts($counts->added, $counts->changed, $deleted, $counts->unchanged);
    }

    /**
     * База містить лише робочі пункти, відкриті для покупців, а інших API не знає; дельта
     * несе й решту, які висіли б у кошику як пункти, на які не можна створити накладну.
     */
    private static function open(array $item): bool
    {
        return ($item['status'] ?? null) === 'Working' && ($item['customerServiceAvailable'] ?? true) !== false;
    }

    /** @return \Generator<array> */
    private function ukrainian(string $file): \Generator
    {
        foreach (JsonItemStream::items($file, self::UKRAINE) as $item) {
            if (($item['countryCode'] ?? null) === 'UA' && isset($item['id'])) {
                yield $item;
            }
        }
    }

    /** @return array<int, array{name:?string, shortName:?string, address:?string}> лише те, що бере рядок */
    private function english(?string $file): array
    {
        $english = [];
        if ($file !== null) {
            foreach ($this->ukrainian($file) as $item) {
                $english[(int)$item['id']] = [
                    'name' => $item['name'] ?? null,
                    'shortName' => $item['shortName'] ?? null,
                    'address' => $item['address'] ?? null,
                ];
            }
        }

        return $english;
    }
}
