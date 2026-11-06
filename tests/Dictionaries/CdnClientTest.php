<?php

namespace Modules\Sviat\NovaPoshta\Dictionaries;

require_once __DIR__ . '/../Support/FakeTransport.php';
require_once __DIR__ . '/../Support/FakeDownloader.php';

use Modules\Sviat\NovaPoshta\Support\FakeDownloader;
use Modules\Sviat\NovaPoshta\Support\FakeTransport;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\HttpResponse;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\TransportException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use Okay\Modules\Sviat\NovaPoshta\Dictionaries\Cdn\CdnClient;
use PHPUnit\Framework\TestCase;

class CdnClientTest extends TestCase
{
    private const string CDN = 'https://api-cdn.novapost.com/dictionary';

    private FakeTransport $http;
    private FakeDownloader $downloader;

    protected function setUp(): void
    {
        $this->http = new FakeTransport();
        $this->downloader = new FakeDownloader();
    }

    private function client(): CdnClient
    {
        return new CdnClient($this->http, $this->downloader);
    }

    private function kind(callable $call): NpErrorKind
    {
        try {
            $call();
        } catch (NpApiException $e) {
            return $e->kind;
        }
        self::fail('expected NpApiException');
    }

    public function testDivisionVersions(): void
    {
        $this->http->json(200, [
            'base_version' => ['unix_time' => 1793898061, 'url' => self::CDN . '/divisions/api/uk/base.json.gz'],
            'deltas' => [
                ['unix_time_from' => 1793725259, 'unix_time_till' => 1793811653, 'url' => self::CDN . '/divisions/api/uk/delta_1793811653.json.gz'],
                ['unix_time_from' => 1793638852, 'unix_time_till' => 1793725259, 'url' => self::CDN . '/divisions/api/uk/delta_1793725259.json.gz'],
            ],
        ]);

        $versions = $this->client()->divisionVersions('uk');

        self::assertSame(self::CDN . '/divisions/api/uk/versions.json', $this->http->requests()[0]->url);
        self::assertSame(1793898061, $versions->baseTime);
        self::assertSame(self::CDN . '/divisions/api/uk/base.json.gz', $versions->baseUrl);
        self::assertSame([1793638852, 1793725259], array_column($versions->deltas, 'from'), 'oldest first');
        self::assertSame(1793811653, $versions->deltas[1]['till']);
    }

    public function testSettlementVersions(): void
    {
        $this->http->json(200, [
            'unix_time' => 1793937611,
            'base_version' => ['unix_time' => 1793937611],
            'urls' => [self::CDN . '/settlements/api/UA/en/base.json.gz'],
        ]);

        $versions = $this->client()->settlementVersions('en');

        self::assertSame(self::CDN . '/settlements/api/UA/en/versions.json', $this->http->requests()[0]->url);
        self::assertSame(1793937611, $versions->baseTime);
        self::assertSame(self::CDN . '/settlements/api/UA/en/base.json.gz', $versions->baseUrl);
        self::assertSame([], $versions->deltas);
    }

    public function testOnlyKnownLanguages(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client()->divisionVersions('../x');
    }

    public function testForeignHostIsRefused(): void
    {
        $this->http->json(200, ['base_version' => ['unix_time' => 1, 'url' => 'https://evil.example/base.json.gz'], 'deltas' => []]);

        self::assertSame(NpErrorKind::Malformed, $this->kind(fn() => $this->client()->divisionVersions('uk')));
    }

    public function testPlainHttpIsRefused(): void
    {
        self::assertSame(NpErrorKind::Malformed, $this->kind(fn() => $this->client()->download('http://api-cdn.novapost.com/dictionary/x.json.gz')));
        self::assertSame([], $this->downloader->calls);
    }

    public function testMissingVersionsFile(): void
    {
        $this->http->push(new HttpResponse(404, 'Not Found'));

        self::assertSame(NpErrorKind::Unavailable, $this->kind(fn() => $this->client()->divisionVersions('uk')));
    }

    public function testBrokenVersionsFile(): void
    {
        $this->http->json(200, ['something' => 'else']);

        self::assertSame(NpErrorKind::Malformed, $this->kind(fn() => $this->client()->divisionVersions('uk')));
    }

    public function testDownloadReturnsATempFile(): void
    {
        $url = self::CDN . '/divisions/api/uk/base.json.gz';
        $this->downloader->files[$url] = 'gz-bytes';

        $path = $this->client()->download($url);

        self::assertSame('gz-bytes', file_get_contents($path));
        self::assertStringStartsWith(sys_get_temp_dir(), $path);
        unlink($path);
    }

    public function testFailedDownloadLeavesNoFile(): void
    {
        $url = self::CDN . '/divisions/api/uk/base.json.gz';
        $this->downloader->files[$url] = new TransportException('cut off');

        self::assertSame(NpErrorKind::Unavailable, $this->kind(fn() => $this->client()->download($url)));
        self::assertFileDoesNotExist($this->downloader->calls[0]['path']);
    }
}
