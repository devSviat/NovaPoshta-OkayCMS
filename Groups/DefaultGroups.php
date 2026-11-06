<?php

namespace Okay\Modules\Sviat\NovaPoshta\Groups;

/** The groups a fresh install starts with (design §6). */
final class DefaultGroups
{
    /** @return list<array{position:int, enabled:bool, categories:array<string,bool>, names:array{uk:string, en:string}}> */
    public static function rows(): array
    {
        return [
            [
                'position' => 1,
                'enabled' => true,
                'categories' => [
                    DivisionCategory::PostBranch->value => true,
                    DivisionCategory::CargoBranch->value => true,
                    DivisionCategory::PUDO->value => true,
                ],
                'names' => ['uk' => 'Відділення', 'en' => 'Branch'],
            ],
            [
                'position' => 2,
                'enabled' => true,
                'categories' => [DivisionCategory::Postomat->value => true],
                'names' => ['uk' => 'Поштомат', 'en' => 'Parcel locker'],
            ],
        ];
    }

    /** English for an English site language, Ukrainian for any other: the manager renames them. */
    public static function name(array $row, string $languageLabel): string
    {
        return $languageLabel === 'en' ? $row['names']['en'] : $row['names']['uk'];
    }
}
