<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries;

use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Rows\DivisionRow;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Store\DictionaryStore;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Stream\JsonItemStream;

final class DivisionsUpdater
{
    /** The CDN divisions files hold every country: only Ukraine is decoded. */
    private const string UKRAINE = '"countryCode":"UA"';

    public function __construct(private readonly DictionaryStore $store)
    {
    }

    /** Whole base: rows absent from it are deleted, but only after the file was read to the end. */
    public function full(string $ukFile, ?string $enFile, int $run): UpdateCounts
    {
        $english = $this->english($enFile);
        $loader = new TableLoader($this->store, DictionaryStore::DIVISIONS, $run, touchUnchanged: true);
        foreach ($this->ukrainian($ukFile) as $item) {
            $loader->put(DivisionRow::fromNp($item, $english[$item['id']] ?? null));
        }
        $counts = $loader->finish();
        $loader->assertPlausibleFullFile();

        $deleted = $this->store->deleteNotSeen(DictionaryStore::DIVISIONS, $run);
        $this->store->refreshPickup();

        return new UpdateCounts($counts->added, $counts->changed, $deleted, $counts->unchanged);
    }

    /** A daily delta: records with `deletedAt` go, the rest are written with their status. */
    public function delta(string $ukFile, ?string $enFile, int $run): UpdateCounts
    {
        $english = $this->english($enFile);
        $loader = new TableLoader($this->store, DictionaryStore::DIVISIONS, $run, touchUnchanged: false);
        $deletedIds = [];
        foreach ($this->ukrainian($ukFile) as $item) {
            if (!empty($item['deletedAt'])) {
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

    /** @return \Generator<array> */
    private function ukrainian(string $file): \Generator
    {
        foreach (JsonItemStream::items($file, self::UKRAINE) as $item) {
            if (($item['countryCode'] ?? null) === 'UA' && isset($item['id'])) {
                yield $item;
            }
        }
    }

    /** @return array<int, array{name:?string, shortName:?string, address:?string}> only what a row takes */
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
