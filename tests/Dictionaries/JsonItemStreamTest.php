<?php

namespace Modules\Sviat\NovaPoshta\Dictionaries;

use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Stream\JsonItemStream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JsonItemStreamTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        array_map(unlink(...), $this->files);
    }

    private function gz(string $json): string
    {
        $path = tempnam(sys_get_temp_dir(), 'np-stream-test-');
        file_put_contents($path, gzencode($json));
        $this->files[] = $path;

        return $path;
    }

    private static function items(): array
    {
        return [
            ['id' => 1, 'countryCode' => 'UA', 'name' => 'Відділення №1 {центр}', 'nested' => ['a' => [1, 2, ['b' => '}]']]]],
            ['id' => 2, 'countryCode' => 'PL', 'name' => 'Oddział "Wrocław" \\ 1'],
            ['id' => 3, 'countryCode' => 'UA', 'name' => 'Пункт \\"лапки\\" і \\\\ слеш', 'schedule' => []],
            // An escaped quote followed by brackets: a scanner that ends the string at \" would close the item here.
            ['id' => 4, 'countryCode' => 'UA', 'name' => 'Кафе "Ріг } ] {" і \\', 'note' => '\\"}'],
        ];
    }

    private static function json(array $items, array $extra = []): string
    {
        return json_encode(['items' => $items] + $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function testYieldsEveryItem(): void
    {
        $got = iterator_to_array(JsonItemStream::items($this->gz(self::json(self::items()))), false);

        self::assertSame(self::items(), $got);
    }

    public static function chunkSizes(): array
    {
        return array_map(static fn(int $n): array => [$n], range(1, 7));
    }

    #[DataProvider('chunkSizes')]
    public function testChunkBoundaries(int $chunk): void
    {
        $got = iterator_to_array(JsonItemStream::items($this->gz(self::json(self::items())), null, $chunk), false);

        self::assertSame(self::items(), $got);
    }

    public function testFilterSkipsOtherCountries(): void
    {
        $got = iterator_to_array(JsonItemStream::items($this->gz(self::json(self::items())), '"countryCode":"UA"', 5), false);

        self::assertSame([1, 3, 4], array_column($got, 'id'));
    }

    public function testObjectsOutsideItemsAreNotItems(): void
    {
        $json = self::json([['id' => 7]], ['meta' => ['id' => 99], 'total' => 1]);

        self::assertSame([['id' => 7]], iterator_to_array(JsonItemStream::items($this->gz($json)), false));
    }

    public function testTruncatedFileFails(): void
    {
        $json = self::json(self::items());

        $this->expectException(\UnexpectedValueException::class);
        iterator_to_array(JsonItemStream::items($this->gz(substr($json, 0, (int)(strlen($json) * 0.6)))));
    }

    public function testBrokenItemFails(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('item 2');
        iterator_to_array(JsonItemStream::items($this->gz('{"items":[{"id":1},{"id":2,"x":tru}]}')));
    }

    public function testEmptyItems(): void
    {
        self::assertSame([], iterator_to_array(JsonItemStream::items($this->gz('{"items":[],"total":0}'))));
    }

    public function testMissingFileFails(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        iterator_to_array(JsonItemStream::items('/nonexistent/np.json.gz'));
    }
}
