<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries\Stream;

/**
 * Reads `{"items":[{…},{…}]}` з файлу .gz по одному елементу.
 *
 * База пунктів Нової Пошти — це ~400 MB JSON з усіма країнами впереміш, тож її не можна
 * декодувати цілком. Сканер відстежує лише рядки та вкладеність; кожен елемент декодується
 * окремо, а елементи без $mustContain пропускаються до декодування.
 */
final class JsonItemStream
{
    /**
     * @return \Generator<int, array>
     * @throws \UnexpectedValueException відсутній файл, зламаний елемент або файл, обірваний посередині
     */
    public static function items(string $gzPath, ?string $mustContain = null, int $chunkBytes = 1048576): \Generator
    {
        $file = is_file($gzPath) ? gzopen($gzPath, 'rb') : false;
        if ($file === false) {
            throw new \UnexpectedValueException("Cannot open {$gzPath}");
        }

        try {
            /** @var list<string> $containers відкриті `{` / `[` ззовні всередину */
            $containers = [];
            $inString = false;
            $escaped = false;
            $item = null;
            $index = 0;

            while (!gzeof($file)) {
                $chunk = gzread($file, $chunkBytes);
                if ($chunk === false) {
                    throw new \UnexpectedValueException("Cannot read {$gzPath}");
                }
                $length = strlen($chunk);
                $position = 0;
                $itemStart = $item === null ? null : 0;

                while ($position < $length) {
                    if ($escaped) {
                        $escaped = false;
                        $position++;
                        continue;
                    }
                    if ($inString) {
                        $position += strcspn($chunk, "\"\\", $position);
                        if ($position >= $length) {
                            break;
                        }
                        if ($chunk[$position] === '\\') {
                            $escaped = true;
                        } else {
                            $inString = false;
                        }
                        $position++;
                        continue;
                    }

                    $position += strcspn($chunk, "\"{}[]", $position);
                    if ($position >= $length) {
                        break;
                    }
                    $char = $chunk[$position];

                    if ($char === '"') {
                        $inString = true;
                    } elseif ($char === '{' || $char === '[') {
                        // Елемент — це об'єкт безпосередньо в масиві "items" верхнього рівня.
                        if ($char === '{' && $containers === ['{', '[']) {
                            $item = '';
                            $itemStart = $position;
                        }
                        $containers[] = $char;
                    } else {
                        array_pop($containers);
                        if ($char === '}' && $containers === ['{', '['] && $item !== null) {
                            $item .= substr($chunk, $itemStart, $position - $itemStart + 1);
                            $index++;
                            if ($mustContain === null || str_contains($item, $mustContain)) {
                                $decoded = json_decode($item, true);
                                if (!is_array($decoded)) {
                                    throw new \UnexpectedValueException("Broken JSON in item {$index} of {$gzPath}");
                                }
                                yield $decoded;
                            }
                            $item = null;
                            $itemStart = null;
                        }
                    }
                    $position++;
                }

                if ($item !== null && $itemStart !== null) {
                    $item .= substr($chunk, $itemStart);
                }
            }

            if ($containers !== [] || $inString || $item !== null) {
                throw new \UnexpectedValueException("{$gzPath} ends in the middle of the data: the download was cut off");
            }
        } finally {
            gzclose($file);
        }
    }
}
