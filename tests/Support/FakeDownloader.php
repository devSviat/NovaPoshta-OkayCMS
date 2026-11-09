<?php

namespace Modules\Sviat\NovaPoshta\Support;

use Okay\Modules\Sviat\NovaPoshta\Api\Http\Downloader;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\TransportException;

final class FakeDownloader implements Downloader
{
    /** @var array<string, string|TransportException> url → вміст файлу або збій */
    public array $files = [];

    /** @var list<array{url:string, path:string, timeout:int}> */
    public array $calls = [];

    public function download(string $url, string $toPath, int $timeoutSeconds): void
    {
        $this->calls[] = ['url' => $url, 'path' => $toPath, 'timeout' => $timeoutSeconds];
        $answer = $this->files[$url] ?? new TransportException("404 {$url}");
        if ($answer instanceof TransportException) {
            throw $answer;
        }
        file_put_contents($toPath, $answer);
    }
}
