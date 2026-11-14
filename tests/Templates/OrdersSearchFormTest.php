<?php

namespace Modules\Sviat\NovaPoshta\Templates;

use Okay\Core\Config;
use Okay\Core\Modules\Module;
use Okay\Core\TplMod\Parser;
use Okay\Core\TplMod\TplMod;
use Okay\Modules\Sviat\NovaPoshta\Init\Init;
use PHPUnit\Framework\TestCase;

/**
 * Пошук у списку замовлень — GET-форма `form.search`: обраний фільтр статусів НП доходить до сервера,
 * лише коли його прихований input лежить усередині цієї форми.
 */
class OrdersSearchFormTest extends TestCase
{
    /** Конструктор Module різний у стоку й форку: заглушка для кожного параметра. */
    private function module(): Module
    {
        $params = new \ReflectionMethod(Module::class, '__construct')->getParameters();

        return new Module(...array_map(fn(\ReflectionParameter $p): object => $this->createStub((string)$p->getType()), $params));
    }

    /** orders.tpl адмінки після модифікацій модуля, так само як їх застосовує Design::applyTplModifiers(). */
    private function modifiedOrdersTpl(): string
    {
        $moduleRoot = dirname((string)new \ReflectionClass(Init::class)->getFileName(), 2);
        $changes = [];
        foreach ($this->module()->getModuleParams('Sviat', 'NovaPoshta')->getBackendModifications() as $modification) {
            if ($modification->getFile() !== 'orders.tpl') {
                continue;
            }
            foreach ($modification->getChanges() as $change) {
                foreach (['Append', 'AppendBefore', 'Prepend', 'AppendAfter'] as $op) {
                    $value = $change->{'get' . $op}();
                    if (!empty($value) && is_file($moduleRoot . '/Backend/design/html/' . $value)) {
                        $change->{'set' . $op}((string)file_get_contents($moduleRoot . '/Backend/design/html/' . $value));
                    }
                }
                $changes[] = $change;
            }
        }
        self::assertNotSame([], $changes);

        $tplMod = new TplMod(new Parser(), $this->createStub(Config::class));
        $tree = new Parser()->parse((string)file_get_contents(dirname(__DIR__, 5) . '/backend/design/html/orders.tpl'));
        new \ReflectionMethod(TplMod::class, 'walkByFile')->invoke($tplMod, $tree, $changes);

        return (string)new \ReflectionMethod(TplMod::class, 'build')->invoke($tplMod, $tree);
    }

    public function testStatusFilterTravelsInsideTheSearchForm(): void
    {
        $html = $this->modifiedOrdersTpl();

        $open = strpos($html, '<form class="search" method="get">');
        self::assertNotFalse($open);
        $close = strpos($html, '</form>', $open);
        self::assertNotFalse($close);
        $form = substr($html, $open, $close - $open);
        self::assertStringContainsString('name="np_waybill" value="{$np_status_group|escape}"', $form);
        self::assertSame(1, substr_count($html, 'name="np_waybill"'), 'one hidden input, inside the form');
    }
}
