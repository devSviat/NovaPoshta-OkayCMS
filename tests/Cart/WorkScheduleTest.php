<?php

namespace Modules\Sviat\NovaPoshta\Cart;

use Okay\Modules\Sviat\NovaPoshta\Cart\WorkSchedule;
use PHPUnit\Framework\TestCase;

class WorkScheduleTest extends TestCase
{
    private static function week(array $hours): string
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $rows = [];
        foreach ($days as $i => $day) {
            if (isset($hours[$i])) {
                $rows[] = ['day' => $day, 'from' => $hours[$i][0], 'to' => $hours[$i][1], 'breakFrom' => '', 'breakTo' => ''];
            }
        }

        return json_encode($rows);
    }

    public function testGroupsEqualDays(): void
    {
        // Відділення №13 у Києві, як його подає Нова Пошта (неділя першою).
        $json = '[{"day":"sunday","from":"08:00","to":"19:00","breakFrom":"","breakTo":""},{"day":"monday","from":"08:00","to":"21:00","breakFrom":"","breakTo":""},{"day":"tuesday","from":"08:00","to":"21:00","breakFrom":"","breakTo":""},{"day":"wednesday","from":"08:00","to":"21:00","breakFrom":"","breakTo":""},{"day":"thursday","from":"08:00","to":"21:00","breakFrom":"","breakTo":""},{"day":"friday","from":"08:00","to":"21:00","breakFrom":"","breakTo":""},{"day":"saturday","from":"08:00","to":"19:00","breakFrom":"","breakTo":""}]';

        self::assertSame([
            ['from' => 0, 'to' => 4, 'hours' => '08:00–21:00'],
            ['from' => 5, 'to' => 6, 'hours' => '08:00–19:00'],
        ], WorkSchedule::groups($json));
    }

    public function testAllDay(): void
    {
        self::assertSame([['allDay' => true]], WorkSchedule::groups(self::week(array_fill(0, 7, ['00:01', '23:59']))));
        self::assertSame([['allDay' => true]], WorkSchedule::groups(self::week(array_fill(0, 7, ['00:00', '24:00']))));
    }

    public function testMissingDayIsDayOff(): void
    {
        $groups = WorkSchedule::groups(self::week(array_fill(0, 6, ['09:00', '18:00'])));

        self::assertSame(['from' => 6, 'to' => 6, 'hours' => null], end($groups));
    }

    public function testDashIsDayOff(): void
    {
        $hours = array_fill(0, 7, ['09:00', '18:00']);
        $hours[2] = ['-', '-'];

        self::assertSame([
            ['from' => 0, 'to' => 1, 'hours' => '09:00–18:00'],
            ['from' => 2, 'to' => 2, 'hours' => null],
            ['from' => 3, 'to' => 6, 'hours' => '09:00–18:00'],
        ], WorkSchedule::groups(self::week($hours)));
    }

    public function testBrokenScheduleGivesNull(): void
    {
        self::assertNull(WorkSchedule::groups('{'));
        self::assertNull(WorkSchedule::groups(''));
        self::assertNull(WorkSchedule::groups('[]'));
        self::assertNull(WorkSchedule::groups('"text"'));
    }
}
