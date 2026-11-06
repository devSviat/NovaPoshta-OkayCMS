<?php

namespace Modules\Sviat\NovaPoshta\Groups;

use Okay\Modules\Sviat\NovaPoshta\Groups\GroupsView;
use PHPUnit\Framework\TestCase;

class GroupsViewTest extends TestCase
{
    private static function groupRows(): array
    {
        return [
            (object)['id' => 1, 'name' => 'Відділення', 'enabled' => 1, 'position' => 1, 'categories' => '{"PostBranch":true,"CargoBranch":true,"PUDO":false}'],
            (object)['id' => 2, 'name' => 'Поштомат', 'enabled' => 0, 'position' => 2, 'categories' => '{"Postomat":true}'],
        ];
    }

    private static function counts(): array
    {
        return ['PostBranch' => 2223, 'CargoBranch' => 1382, 'PUDO' => 11490, 'Postomat' => 40042];
    }

    public function testCategoriesWithTheirGroupAndCount(): void
    {
        $vars = GroupsView::vars(self::groupRows(), self::counts());
        $byKey = array_column($vars['categories'], null, 'key');

        self::assertSame(['PostBranch', 'CargoBranch', 'PUDO', 'Postomat'], array_keys($byKey));
        self::assertSame(1, $byKey['PUDO']['group']);
        self::assertFalse($byKey['PUDO']['shown']);
        self::assertSame(11490, $byKey['PUDO']['count']);
        self::assertSame('sviat__novaposhta__category_pudo', $byKey['PUDO']['label_key']);
    }

    public function testHiddenCategoriesAreNamedWithTheReason(): void
    {
        $byKey = array_column(GroupsView::vars(self::groupRows(), self::counts())['categories'], null, 'key');

        self::assertSame('hidden', $byKey['PUDO']['warning']);
        self::assertSame('group_disabled', $byKey['Postomat']['warning']);
        self::assertNull($byKey['PostBranch']['warning']);
    }

    public function testCategoryInNoGroup(): void
    {
        $groups = self::groupRows();
        $groups[1]->categories = '{}';

        $byKey = array_column(GroupsView::vars($groups, self::counts())['categories'], null, 'key');

        self::assertSame(0, $byKey['Postomat']['group']);
        self::assertSame('no_group', $byKey['Postomat']['warning']);
    }

    public function testBrokenJsonInTheTableIsTreatedAsEmpty(): void
    {
        $groups = self::groupRows();
        $groups[0]->categories = '{broken';

        $vars = GroupsView::vars($groups, self::counts());

        self::assertSame([], $vars['groups'][0]['categories']);
    }
}
