<?php

namespace Modules\Sviat\NovaPoshta\Dictionaries;

require_once __DIR__ . '/../Support/InMemoryDictionaryStore.php';

use Modules\Sviat\NovaPoshta\Support\InMemoryDictionaryStore;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DivisionsUpdater;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\SettlementsUpdater;
use PHPUnit\Framework\TestCase;

class UpdatersTest extends TestCase
{
    private InMemoryDictionaryStore $store;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        $this->store = new InMemoryDictionaryStore();
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), $this->files);
    }

    private function gz(array $items, bool $truncate = false): string
    {
        $json = json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
        $path = tempnam(sys_get_temp_dir(), 'np-upd-test-');
        file_put_contents($path, gzencode($truncate ? substr($json, 0, -40) : $json));
        $this->files[] = $path;

        return $path;
    }

    private static function division(int $id, array $override = []): array
    {
        return $override + [
            'id' => $id, 'countryCode' => 'UA', 'name' => "Відділення №{$id}", 'shortName' => "№{$id}",
            'settlement' => ['id' => 100 + $id], 'number' => "{$id}", 'status' => 'Working', 'customerServiceAvailable' => true, 'divisionCategory' => 'PostBranch',
            'address' => "Адреса {$id}", 'addressParts' => ['postCode' => '01001'], 'deletedAt' => null,
        ];
    }

    private function divisions(): DivisionsUpdater
    {
        return new DivisionsUpdater($this->store);
    }

    public function testFullLoadOnEmptyTable(): void
    {
        $counts = $this->divisions()->full($this->gz([self::division(1), self::division(2), self::division(3, ['countryCode' => 'PL'])]), null, 10);

        self::assertSame([1, 2], array_keys($this->store->tables['divisions']));
        self::assertSame(['added' => 2, 'changed' => 0, 'deleted' => 0, 'unchanged' => 0], $counts->toArray());
    }

    public function testSecondFullLoadWritesNothing(): void
    {
        $file = $this->gz([self::division(1), self::division(2)]);
        $this->divisions()->full($file, null, 10);
        $this->store->calls = [];

        $counts = $this->divisions()->full($file, null, 11);

        self::assertSame(['added' => 0, 'changed' => 0, 'deleted' => 0, 'unchanged' => 2], $counts->toArray());
        self::assertNotContains('upsert divisions 2', $this->store->calls);
    }

    public function testChangedRecordIsRewritten(): void
    {
        $this->divisions()->full($this->gz([self::division(1), self::division(2)]), null, 10);

        $counts = $this->divisions()->full($this->gz([self::division(1), self::division(2, ['name' => 'Нова назва'])]), null, 11);

        self::assertSame(1, $counts->changed);
        self::assertSame('Нова назва', $this->store->tables['divisions'][2]['name_uk']);
    }

    public function testMissingFromBaseIsDeletedOnlyAfterTheWholeFile(): void
    {
        $this->divisions()->full($this->gz([self::division(1), self::division(2)]), null, 10);

        $counts = $this->divisions()->full($this->gz([self::division(1)]), null, 11);

        self::assertSame(1, $counts->deleted);
        self::assertSame([1], array_keys($this->store->tables['divisions']));
        self::assertContains('deleteNotSeen divisions', $this->store->calls);
    }

    public function testInterruptedFullRunDeletesNothing(): void
    {
        $this->divisions()->full($this->gz([self::division(1), self::division(2), self::division(3)]), null, 10);
        $this->store->calls = [];

        try {
            $this->divisions()->full($this->gz([self::division(1)], truncate: true), null, 11);
            self::fail('a cut-off file must fail');
        } catch (\UnexpectedValueException) {
        }

        self::assertCount(3, $this->store->tables['divisions']);
        self::assertNotContains('deleteNotSeen divisions', $this->store->calls);
    }

    public function testStoreFailureStopsBeforeDeleting(): void
    {
        $this->divisions()->full($this->gz([self::division(1), self::division(2)]), null, 10);
        $this->store->failOnUpsert = new \RuntimeException('SQL failed');

        try {
            $this->divisions()->full($this->gz([self::division(1, ['name' => 'x'])]), null, 11);
            self::fail('a store failure must stop the run');
        } catch (\RuntimeException) {
        }

        self::assertCount(2, $this->store->tables['divisions']);
    }

    /** The CDN base holds only working points open to customers; a delta also carries the rest (live CDN, 09.11.2026). */
    public function testFullSkipsPointsClosedToCustomers(): void
    {
        $this->divisions()->full($this->gz([self::division(1), self::division(2, ['customerServiceAvailable' => false]), self::division(3, ['status' => 'NotWorking'])]), null, 10);

        self::assertSame([1], array_keys($this->store->tables['divisions']));
    }

    public function testDeltaKeepsOnlyWhatTheBaseWouldHold(): void
    {
        $this->divisions()->full($this->gz([self::division(1), self::division(2), self::division(3), self::division(5)]), null, 10);
        $this->store->calls = [];

        $counts = $this->divisions()->delta($this->gz([
            self::division(1, ['deletedAt' => '2026-11-05T10:00:00Z']),
            self::division(2, ['status' => 'NotWorkingTemporary']),
            self::division(3, ['customerServiceAvailable' => false]),
            self::division(4, ['status' => 'InProcessOpening']),
            self::division(6, ['customerServiceAvailable' => false]),
            self::division(7),
            self::division(9, ['countryCode' => 'PL', 'deletedAt' => '2026-11-05T10:00:00Z']),
        ]), null, 11);

        self::assertSame([5, 7], array_keys($this->store->tables['divisions']));
        self::assertSame(['added' => 1, 'changed' => 0, 'deleted' => 3, 'unchanged' => 0], $counts->toArray());
        self::assertNotContains('deleteNotSeen divisions', $this->store->calls);
    }

    public function testEnglishNamesAreJoinedById(): void
    {
        $en = $this->gz([self::division(1, ['name' => 'Branch 1', 'shortName' => 'No 1', 'address' => 'Address 1']), self::division(5, ['name' => 'Other'])]);

        $this->divisions()->full($this->gz([self::division(1), self::division(2)]), $en, 10);

        self::assertSame('Branch 1', $this->store->tables['divisions'][1]['name_en']);
        self::assertSame('Address 1', $this->store->tables['divisions'][1]['address_en']);
        self::assertNull($this->store->tables['divisions'][2]['name_en']);
    }

    public function testSettlementsKeepBoostOnReload(): void
    {
        $settlement = ['id' => 118064, 'name' => 'місто Київ', 'region' => ['name' => 'Київська область', 'parent' => null], 'alternativeNames' => ['kyiv']];
        $updater = new SettlementsUpdater($this->store);
        $updater->full($this->gz([$settlement]), null, 10);
        $this->store->setBoost([118064 => 514]);

        $updater->full($this->gz([['name' => 'Київ'] + $settlement]), null, 11);

        self::assertSame(514, $this->store->tables['settlements'][118064]['boost']);
        self::assertSame('Київ', $this->store->tables['settlements'][118064]['name_uk']);
    }

    public function testAFileWithFarFewerItemsDeletesNothing(): void
    {
        $this->divisions()->full($this->gz([self::division(1), self::division(2), self::division(3), self::division(4)]), null, 10);

        try {
            $this->divisions()->full($this->gz([self::division(1)]), null, 11);
            self::fail('a base with a quarter of the rows must not be trusted');
        } catch (\UnexpectedValueException) {
        }

        self::assertCount(4, $this->store->tables['divisions']);
    }

    public function testAnEmptyBaseDeletesNothing(): void
    {
        $this->divisions()->full($this->gz([self::division(1), self::division(2)]), null, 10);

        $this->expectException(\UnexpectedValueException::class);
        try {
            $this->divisions()->full($this->gz([]), null, 11);
        } finally {
            self::assertCount(2, $this->store->tables['divisions']);
        }
    }

    public function testPickupFlagsFollowEveryUpdate(): void
    {
        $this->divisions()->full($this->gz([self::division(1), self::division(2)]), null, 10);
        self::assertContains('refreshPickup', $this->store->calls);

        $this->store->calls = [];
        $this->divisions()->delta($this->gz([self::division(3)]), null, 11);
        self::assertContains('refreshPickup', $this->store->calls);

        $this->store->calls = [];
        (new SettlementsUpdater($this->store))->full($this->gz([['id' => 5, 'name' => 'село Х', 'region' => null]]), null, 12);
        self::assertContains('refreshPickup', $this->store->calls, 'a new settlement needs its flag too');
    }

    public function testRowsAreWrittenInBatches(): void
    {
        $items = array_map(static fn(int $i): array => self::division($i), range(1, 1201));

        $this->divisions()->full($this->gz($items), null, 10);

        self::assertSame(['upsert divisions 500', 'upsert divisions 500', 'upsert divisions 201', 'deleteNotSeen divisions', 'refreshPickup'], $this->store->calls);
    }
}
