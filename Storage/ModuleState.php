<?php

namespace Okay\Modules\Sviat\NovaPoshta\Storage;

use Okay\Core\EntityFactory;

final class ModuleState implements StateStorage
{
    public function __construct(private readonly EntityFactory $entityFactory)
    {
    }

    public function get(string $name): ?string
    {
        $row = $this->entity()->get($name);

        return $row ? $row->value : null;
    }

    public function set(string $name, ?string $value): void
    {
        $entity = $this->entity();
        $data = ['value' => $value, 'updated_at' => date('Y-m-d H:i:s')];
        $row = $entity->get($name);
        if ($row) {
            $entity->update((int)$row->id, $data);
        } else {
            $entity->add(['name' => $name] + $data);
        }
    }

    private function entity(): StateEntity
    {
        return $this->entityFactory->get(StateEntity::class);
    }
}
