<?php

namespace Okay\Modules\Sviat\NovaPoshta\Api\Http;

interface Downloader
{
    /**
     * Зберігає тіло $url у $toPath, не тримаючи його в пам'яті.
     *
     * @throws TransportException не 200, помилка мережі або менше байтів, ніж заявлено
     */
    public function download(string $url, string $toPath, int $timeoutSeconds): void;
}
