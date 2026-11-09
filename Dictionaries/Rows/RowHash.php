<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries\Rows;

final class RowHash
{
    /** Adds `row_hash`: тоді незмінений запис пропускається, а не перезаписується на кожному запуску. */
    public static function with(array $row): array
    {
        $row['row_hash'] = sha1(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

        return $row;
    }
}
