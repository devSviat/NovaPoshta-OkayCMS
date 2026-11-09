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

    /** A daily delta: what the base would not hold (deleted, closed, not open to customers) goes, the rest is written. */
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
     * The base holds only working points open to customers, and the API knows no others; a delta
     * also carries the rest, which would sit in the cart as points no waybill can be made to.
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
