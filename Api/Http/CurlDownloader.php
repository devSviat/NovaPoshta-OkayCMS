<?php

namespace Okay\Modules\Sviat\NovaPoshta\Api\Http;

final class CurlDownloader implements Downloader
{
    public function download(string $url, string $toPath, int $timeoutSeconds): void
    {
        $file = fopen($toPath, 'wb');
        if ($file === false) {
            throw new TransportException("Cannot write {$toPath}");
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_FILE => $file,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_FAILONERROR => true,
        ]);
        $ok = curl_exec($curl);
        $error = curl_error($curl);
        $expected = (int)curl_getinfo($curl, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        fclose($file);

        if ($ok === false) {
            throw new TransportException($error ?: "Download failed: {$url}");
        }
        clearstatcache(true, $toPath);
        if ($expected > 0 && filesize($toPath) !== $expected) {
            throw new TransportException("Download cut off: {$url}");
        }
    }
}
