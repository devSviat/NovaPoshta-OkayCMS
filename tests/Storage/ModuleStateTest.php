<?php

namespace Modules\Sviat\NovaPoshta\Storage;

use Okay\Core\EntityFactory;
use Okay\Modules\Sviat\NovaPoshta\Storage\ModuleState;
use Okay\Modules\Sviat\NovaPoshta\Storage\StateEntity;
use PHPUnit\Framework\TestCase;

/** Бік бази даних перевіряється на стендах; тут — що set() робить upsert за назвою. */
class ModuleStateTest extends TestCase
{
    /** @var array<string, object> */
    private array $rows = [];
    private int $nextId = 1;

    private function state(): ModuleState
    {
        $entity = $this->createStub(StateEntity::class);
        $entity->method('get')->willReturnCallback(fn($name) => $this->rows[$name] ?? false);
        $entity->method('add')->willReturnCallback(function ($row) {
            $row = (object)$row;
            $row->id = $this->nextId++;
            $this->rows[$row->name] = $row;

            return $row->id;
        });
        $entity->method('update')->willReturnCallback(function ($id, $data) {
            foreach ($this->rows as $row) {
                if ($row->id === $id) {
                    foreach ((array)$data as $field => $value) {
                        $row->$field = $value;
                    }
                }
            }

            return true;
        });
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturn($entity);

        return new ModuleState($factory);
    }

    public function testSetTwiceKeepsOneRowWithTheLastValue(): void
    {
        $state = $this->state();
        $state->set('x', '1');
        $state->set('x', '2');

        self::assertSame('2', $state->get('x'));
        self::assertCount(1, $this->rows);
    }

    public function testMissingNameIsNull(): void
    {
        self::assertNull($this->state()->get('missing'));
    }

    public function testNullValueIsStoredAsNull(): void
    {
        $state = $this->state();
        $state->set('x', '1');
        $state->set('x', null);

        self::assertNull($state->get('x'));
    }
}
