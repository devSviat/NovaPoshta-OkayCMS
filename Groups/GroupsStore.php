<?php

namespace Okay\Modules\Sviat\NovaPoshta\Groups;

use Okay\Core\EntityFactory;
use Okay\Modules\Sviat\NovaPoshta\Entities\GroupEntity;

/** Group names are written in the content language selected in the admin, like other OkayCMS content. */
final class GroupsStore
{
    public function __construct(private readonly EntityFactory $entityFactory)
    {
    }

    /** @return list<object> */
    public function all(): array
    {
        return array_values($this->entity()->find());
    }

    /** @param list<array{id:?int, name:string, enabled:bool, position:int, delete:bool, categories:array<string,bool>}> $groups from GroupsInput */
    public function save(array $groups): void
    {
        $entity = $this->entity();
        foreach ($groups as $group) {
            if ($group['delete']) {
                if ($group['id'] !== null) {
                    $entity->delete($group['id']);
                }
                continue;
            }
            $row = [
                'name' => $group['name'],
                'enabled' => (int)$group['enabled'],
                'position' => $group['position'],
                'categories' => json_encode((object)$group['categories']),
            ];
            $group['id'] === null ? $entity->add($row) : $entity->update($group['id'], $row);
        }
    }

    private function entity(): GroupEntity
    {
        return $this->entityFactory->get(GroupEntity::class);
    }
}
