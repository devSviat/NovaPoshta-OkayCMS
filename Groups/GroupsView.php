<?php

namespace Okay\Modules\Sviat\NovaPoshta\Groups;

/** Template data for the groups block; says plainly which categories customers will not see. */
final class GroupsView
{
    /**
     * @param list<object> $groups rows of GroupEntity
     * @param array<string, int> $counts working points per category
     */
    public static function vars(array $groups, array $counts): array
    {
        $groupVars = [];
        foreach ($groups as $group) {
            $categories = json_decode((string)$group->categories, true);
            $groupVars[] = [
                'id' => (int)$group->id,
                'name' => (string)$group->name,
                'enabled' => (bool)$group->enabled,
                'position' => (int)$group->position,
                'categories' => is_array($categories) ? array_map(boolval(...), $categories) : [],
            ];
        }

        $categoryVars = [];
        foreach (DivisionCategory::cases() as $category) {
            $owner = null;
            foreach ($groupVars as $group) {
                if (array_key_exists($category->value, $group['categories'])) {
                    $owner = $group;
                    break;
                }
            }
            $shown = $owner !== null && $owner['categories'][$category->value];
            $categoryVars[] = [
                'key' => $category->value,
                'label_key' => 'sviat__novaposhta__category_' . strtolower($category->value),
                'count' => $counts[$category->value] ?? 0,
                'group' => $owner['id'] ?? 0,
                'shown' => $shown,
                'warning' => match (true) {
                    $owner === null => 'no_group',
                    !$owner['enabled'] => 'group_disabled',
                    !$shown => 'hidden',
                    default => null,
                },
            ];
        }

        return ['groups' => $groupVars, 'categories' => $categoryVars];
    }
}
