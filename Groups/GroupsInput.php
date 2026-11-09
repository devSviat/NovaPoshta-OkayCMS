<?php

namespace Okay\Modules\Sviat\NovaPoshta\Groups;

/**
 * Форма груп у надісланому вигляді: `groups[i][id|name|enabled|delete]` (id "new…" для групи, доданої
 * на сторінці) і `categories[Category][group|shown]`, де `group` посилається на id у формі.
 */
final class GroupsInput
{
    private const int NAME_LENGTH = 255;

    /** @return array{groups: list<array{id:?int, name:string, enabled:bool, position:int, delete:bool, categories:array<string,bool>}>, errors: list<string>} */
    public static function parse(array $post): array
    {
        $rawGroups = is_array($post['groups'] ?? null) ? array_values($post['groups']) : [];
        if ($rawGroups === []) {
            return ['groups' => [], 'errors' => ['no_groups']];
        }

        $groups = [];
        $indexByFormId = [];
        $errors = [];
        $rawCategories = is_array($post['categories'] ?? null) ? $post['categories'] : [];
        $referenced = array_map(static fn($c): string => is_array($c) ? (string)($c['group'] ?? '') : '', $rawCategories);
        foreach ($rawGroups as $raw) {
            $raw = is_array($raw) ? $raw : [];
            $formId = (string)($raw['id'] ?? '');
            $name = mb_substr(trim((string)($raw['name'] ?? '')), 0, self::NAME_LENGTH);
            $delete = !empty($raw['delete']);
            // Сторінка завжди містить один порожній рядок нової групи; якщо його лишено порожнім, це не група.
            if (!ctype_digit($formId) && $name === '' && !in_array($formId, $referenced, true)) {
                continue;
            }
            $i = count($groups);
            if ($name === '' && !$delete) {
                $errors[] = 'empty_name';
            }
            $indexByFormId[$formId] = $i;
            $groups[] = [
                'id' => ctype_digit($formId) && (int)$formId > 0 ? (int)$formId : null,
                'name' => $name,
                'enabled' => !empty($raw['enabled']),
                'position' => $i + 1,
                'delete' => $delete,
                'categories' => [],
            ];
        }

        foreach (DivisionCategory::cases() as $category) {
            $raw = $rawCategories[$category->value] ?? null;
            $ref = is_array($raw) ? (string)($raw['group'] ?? '') : '';
            if ($ref === '' || $ref === '0') {
                continue;
            }
            if (!isset($indexByFormId[$ref])) {
                $errors[] = 'unknown_group';
                continue;
            }
            $index = $indexByFormId[$ref];
            if ($groups[$index]['delete']) {
                $errors[] = 'category_in_deleted_group';
                continue;
            }
            $groups[$index]['categories'][$category->value] = !empty($raw['shown']);
        }

        return ['groups' => $groups, 'errors' => array_values(array_unique($errors))];
    }
}
