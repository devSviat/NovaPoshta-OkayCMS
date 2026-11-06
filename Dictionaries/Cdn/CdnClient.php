<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries\Cdn;

use Okay\Modules\Sviat\NovaPoshta\Api\Http\Downloader;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\HttpRequest;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\HttpTransport;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\TransportException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;

/** Public Nova Poshta dictionary files; no API key needed. */
final class CdnClient
{
    public const string BASE = 'https://api-cdn.novapost.com/dictionary';

    private const array LANGUAGES = ['uk', 'en'];
    private const int DOWNLOAD_TIMEOUT = 600;

    public function __construct(
        private readonly HttpTransport $http,
        private readonly Downloader $downloader,
    ) {
    }

    public function divisionVersions(string $lang): CdnVersions
    {
        $json = $this->versionsJson(self::BASE . '/divisions/api/' . self::language($lang) . '/versions.json');

        return $this->checked(CdnVersions::fromDivisions($json));
    }

    public function settlementVersions(string $lang): CdnVersions
    {
        $json = $this->versionsJson(self::BASE . '/settlements/api/UA/' . self::language($lang) . '/versions.json');

        return $this->checked(CdnVersions::fromSettlements($json));
    }

    /** @return string path of a temp file the caller deletes */
    public function download(string $url): string
    {
        self::assertCdnUrl($url);
        $path = tempnam(sys_get_temp_dir(), 'np-dict-');
        try {
            $this->downloader->download($url, $path, self::DOWNLOAD_TIMEOUT);
        } catch (TransportException $e) {
            @unlink($path);
            throw NpApiException::fromNp(NpErrorKind::Unavailable, [$e->getMessage()]);
        }

        return $path;
    }

    private function versionsJson(string $url): array
    {
        try {
            $response = $this->http->send(new HttpRequest('GET', $url, ['Accept' => 'application/json']));
        } catch (TransportException $e) {
            throw NpApiException::fromNp(NpErrorKind::Unavailable, [$e->getMessage()]);
        }
        if ($response->status !== 200) {
            throw NpApiException::fromNp(NpErrorKind::Unavailable, ["HTTP {$response->status} {$url}"]);
        }

        return $response->json() ?? throw NpApiException::fromNp(NpErrorKind::Malformed, ["Not JSON: {$url}"]);
    }

    private function checked(?CdnVersions $versions): CdnVersions
    {
        if ($versions === null) {
            throw NpApiException::fromNp(NpErrorKind::Malformed, ['Unexpected versions.json']);
        }
        self::assertCdnUrl($versions->baseUrl);
        foreach ($versions->deltas as $delta) {
            self::assertCdnUrl($delta['url']);
        }

        return $versions;
    }

    /** versions.json decides what is downloaded, so only the CDN itself, over https, is accepted. */
    private static function assertCdnUrl(string $url): void
    {
        if (!str_starts_with($url, self::BASE . '/')) {
            throw NpApiException::fromNp(NpErrorKind::Malformed, ["Not a Nova Poshta CDN url: {$url}"]);
        }
    }

    private static function language(string $lang): string
    {
        if (!in_array($lang, self::LANGUAGES, true)) {
            throw new \InvalidArgumentException("Unsupported dictionary language: {$lang}");
        }

        return $lang;
    }
}
