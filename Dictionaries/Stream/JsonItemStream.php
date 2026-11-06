<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries\Stream;

/**
 * Reads `{"items":[{…},{…}]}` from a .gz file one item at a time.
 *
 * The Nova Poshta divisions base is ~400 MB of JSON with every country mixed in, so it can
 * not be decoded whole. The scanner only tracks strings and nesting; each item is decoded
 * on its own, and items without $mustContain are skipped before decoding.
 */
final class JsonItemStream
{
    /**
     * @return \Generator<int, array>
     * @throws \UnexpectedValueException missing file, broken item, or file cut off mid-way
     */
    public static function items(string $gzPath, ?string $mustContain = null, int $chunkBytes = 1048576): \Generator
    {
        $file = is_file($gzPath) ? gzopen($gzPath, 'rb') : false;
        if ($file === false) {
            throw new \UnexpectedValueException("Cannot open {$gzPath}");
        }

        try {
            /** @var list<string> $containers open `{` / `[` from the outside in */
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
                        // An item is an object directly inside the top-level "items" array.
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
