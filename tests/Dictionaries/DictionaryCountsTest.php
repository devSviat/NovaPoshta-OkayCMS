<?php

namespace Modules\Sviat\NovaPoshta\Dictionaries;

use Okay\Modules\Sviat\NovaPoshta\Dictionaries\DictionaryCounts;
use PHPUnit\Framework\TestCase;

class DictionaryCountsTest extends TestCase
{
    public function testGroupedRowsCoverEveryCategory(): void
    {
        $counts = DictionaryCounts::fromRows([
            (object)['category' => 'Postomat', 'n' => '40035'],
            (object)['category' => 'PostBranch', 'n' => '2224'],
            (object)['category' => 'Unknown', 'n' => '7'],
        ]);

        self::assertSame(['PostBranch' => 2224, 'CargoBranch' => 0, 'PUDO' => 0, 'Postomat' => 40035], $counts);
    }
}
