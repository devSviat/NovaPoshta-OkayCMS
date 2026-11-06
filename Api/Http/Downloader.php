<?php

namespace Okay\Modules\Sviat\NovaPoshta\Api\Http;

interface Downloader
{
    /**
     * Saves the body of $url to $toPath without holding it in memory.
     *
     * @throws TransportException not 200, network error, or fewer bytes than announced
     */
    public function download(string $url, string $toPath, int $timeoutSeconds): void;
}
