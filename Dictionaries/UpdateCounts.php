<?php

namespace Okay\Modules\Sviat\NovaPoshta\Dictionaries;

final readonly class UpdateCounts
{
    public function __construct(
        public int $added = 0,
        public int $changed = 0,
        public int $deleted = 0,
        public int $unchanged = 0,
    ) {
    }

    /** @return array{added:int, changed:int, deleted:int, unchanged:int} */
    public function toArray(): array
    {
        return ['added' => $this->added, 'changed' => $this->changed, 'deleted' => $this->deleted, 'unchanged' => $this->unchanged];
    }
}
