<?php

namespace Modules\Sviat\NovaPoshta\Groups;

use Okay\Modules\Sviat\NovaPoshta\Groups\GroupsInput;
use PHPUnit\Framework\TestCase;

/** POST форми груп → групи для збереження або помилки. */
class GroupsInputTest extends TestCase
{
    private static function post(): array
    {
        return [
            'groups' => [
                ['id' => '1', 'name' => ' Відділення ', 'enabled' => '1'],
                ['id' => '2', 'name' => 'Поштомат'],
            ],
            'categories' => [
                'PostBranch' => ['group' => '1', 'shown' => '1'],
                'CargoBranch' => ['group' => '1'],
                'PUDO' => ['group' => '0', 'shown' => '1'],
                'Postomat' => ['group' => '2', 'shown' => '1'],
                'Hacked' => ['group' => '1', 'shown' => '1'],
            ],
        ];
    }

    public function testNormalizesGroupsInFormOrder(): void
    {
        $result = GroupsInput::parse(self::post());

        self::assertSame([], $result['errors']);
        self::assertSame([
            ['id' => 1, 'name' => 'Відділення', 'enabled' => true, 'position' => 1, 'delete' => false, 'categories' => ['PostBranch' => true, 'CargoBranch' => false]],
            ['id' => 2, 'name' => 'Поштомат', 'enabled' => false, 'position' => 2, 'delete' => false, 'categories' => ['Postomat' => true]],
        ], $result['groups']);
    }

    public function testCategoryWithoutGroupAndUnknownCategoryAreDropped(): void
    {
        $json = json_encode(GroupsInput::parse(self::post())['groups']);

        self::assertStringNotContainsString('PUDO', $json);
        self::assertStringNotContainsString('Hacked', $json);
    }

    public function testEmptyNameIsAnError(): void
    {
        $post = self::post();
        $post['groups'][1]['name'] = '   ';

        self::assertSame(['empty_name'], GroupsInput::parse($post)['errors']);
    }

    public function testCategoryOfAMissingGroupIsAnError(): void
    {
        $post = self::post();
        $post['categories']['Postomat']['group'] = '77';

        self::assertSame(['unknown_group'], GroupsInput::parse($post)['errors']);
    }

    public function testNewGroupAndDeletion(): void
    {
        $post = self::post();
        $post['groups'][] = ['id' => 'new', 'name' => 'Вантажні', 'enabled' => '1'];
        $post['groups'][1]['delete'] = '1';
        $post['categories']['CargoBranch']['group'] = 'new';
        $post['categories']['Postomat']['group'] = '0';

        $groups = GroupsInput::parse($post)['groups'];

        self::assertNull($groups[2]['id']);
        self::assertSame(['CargoBranch' => false], $groups[2]['categories']);
        self::assertTrue($groups[1]['delete']);
    }

    public function testUntouchedNewRowIsIgnored(): void
    {
        $post = self::post();
        $post['groups'][] = ['id' => 'new', 'name' => ' ', 'enabled' => '1'];

        $result = GroupsInput::parse($post);

        self::assertSame([], $result['errors']);
        self::assertCount(2, $result['groups']);
    }

    public function testCategoryInADeletedGroupIsAnError(): void
    {
        $post = self::post();
        $post['groups'][1]['delete'] = '1';

        self::assertSame(['category_in_deleted_group'], GroupsInput::parse($post)['errors']);
    }

    public function testGarbageIsNotAFatal(): void
    {
        self::assertSame(['no_groups'], GroupsInput::parse(['groups' => 'x', 'categories' => 7])['errors']);
        self::assertSame(['no_groups'], GroupsInput::parse([])['errors']);
    }

    public function testLongNameIsCut(): void
    {
        $post = self::post();
        $post['groups'][0]['name'] = str_repeat('ї', 300);

        self::assertSame(255, mb_strlen(GroupsInput::parse($post)['groups'][0]['name']));
    }
}
