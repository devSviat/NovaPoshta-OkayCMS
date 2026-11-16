<?php

namespace Modules\Sviat\NovaPoshta\Orders;

require_once __DIR__ . '/../Support/InMemoryWaybillRows.php';
require_once __DIR__ . '/../Support/RequestStub.php';

use Modules\Sviat\NovaPoshta\Support\InMemoryWaybillRows;
use Modules\Sviat\NovaPoshta\Support\RequestStub;
use Okay\Core\BackendTranslations;
use Okay\Core\Modules\Modules;
use Okay\Core\Design;
use Okay\Modules\Sviat\DeliveryFilter\Helpers\DeliveryStatusGroups;
use Okay\Modules\Sviat\NovaPoshta\Extenders\BackendExtender;
use Okay\Modules\Sviat\NovaPoshta\Extenders\DeliveryFilterExtender;
use Okay\Modules\Sviat\NovaPoshta\Orders\DeliveryFilterHub;
use Okay\Modules\Sviat\NovaPoshta\Orders\OrdersListFilter;
use Okay\Modules\Sviat\NovaPoshta\Waybills\WaybillStatus;
use PHPUnit\Framework\TestCase;

/**
 * Нова Пошта в хабі Sviat/DeliveryFilter: група `np`, статус → фільтр сутності, пошук ТТН. Без хаба (його немає
 * або він вимкнений) список замовлень поводиться рівно як до хаба: свій select і свій пошук ТТН.
 */
class DeliveryFilterHooksTest extends TestCase
{
    private const string HUB = 'Okay\\Modules\\Sviat\\DeliveryFilter\\Helpers\\DeliveryFilterHelper';

    private InMemoryWaybillRows $rows;
    /** @var array<string, mixed> */
    private array $assigned = [];

    protected function setUp(): void
    {
        $this->rows = new InMemoryWaybillRows();
        $this->rows->rows[990061] = (object)['number' => '20451234567890'];
        $this->rows->rows[990062] = (object)['number' => '20450000000001'];
        $_SESSION = [];
    }

    private function translations(): BackendTranslations
    {
        $lang = [];
        include dirname(__DIR__, 5) . '/Okay/Modules/Sviat/NovaPoshta/Backend/lang/ua.php';
        $stub = $this->createStub(BackendTranslations::class);
        $stub->method('getTranslation')->willReturnCallback(static fn(string $key): string => $lang[$key] ?? '');

        return $stub;
    }

    private function hooks(bool $active = true): DeliveryFilterExtender
    {
        return new DeliveryFilterExtender($this->rows, $this->translations(), $this->modules($active));
    }

    private function modules(bool $active): Modules
    {
        $modules = $this->createStub(Modules::class);
        $modules->method('isActiveModule')->willReturnCallback(static fn($vendor, $name): bool => $active && $vendor === 'Sviat' && $name === 'NovaPoshta');

        return $modules;
    }

    private function hub(bool $active): DeliveryFilterHub
    {
        $hub = $this->createStub(DeliveryFilterHub::class);
        $hub->method('active')->willReturn($active);

        return $hub;
    }

    /** Лише поля, яких торкаються listFilter і countFilter; решта конструктора тут не потрібна. */
    private function backend(bool $hubActive, array $get): BackendExtender
    {
        $design = $this->createStub(Design::class);
        $design->method('assign')->willReturnCallback(function ($name, $value = null) use ($design) {
            $this->assigned[$name] = $value;

            return $design;
        });
        $extender = new \ReflectionClass(BackendExtender::class)->newInstanceWithoutConstructor();
        $deps = ['design' => $design, 'request' => new RequestStub([], $get, 'GET'), 'waybillRows' => $this->rows, 'translations' => $this->translations(), 'hub' => $this->hub($hubActive)];
        \Closure::bind(function () use ($deps): void {
            foreach ($deps as $name => $value) {
                $this->{$name} = $value;
            }
        }, $extender, BackendExtender::class)();

        return $extender;
    }

    public function testGroupDescribesEveryFilterGroup(): void
    {
        $filters = $this->hooks()->deliveryStatusFilters(['up' => ['title' => 'Укрпошта']]);

        self::assertSame(['up', 'np'], array_keys($filters));
        self::assertSame('Нова Пошта', $filters['np']['title']);
        self::assertSame('НП', $filters['np']['short']);
        self::assertSame('np_waybill', $filters['np']['legacy_param']);
        self::assertSame(array_keys(WaybillStatus::FILTER_GROUPS), array_keys($filters['np']['options']));
        self::assertSame('Прибула', $filters['np']['options']['arrived']);
        self::assertSame('Без накладної', $filters['np']['options']['none']);
    }

    public function testGroupPassesTheHubWhitelist(): void
    {
        if (!class_exists(DeliveryStatusGroups::class)) {
            self::markTestSkipped('хаба Sviat/DeliveryFilter тут немає');
        }
        foreach (['ua', 'en', 'ru'] as $label) {
            $lang = [];
            include dirname(__DIR__, 5) . "/Okay/Modules/Sviat/NovaPoshta/Backend/lang/{$label}.php";
            $translations = $this->createStub(BackendTranslations::class);
            $translations->method('getTranslation')->willReturnCallback(static fn(string $key): string => $lang[$key] ?? '');
            $filters = new DeliveryFilterExtender($this->rows, $translations, $this->modules(true))->deliveryStatusFilters([]);

            self::assertSame(['np'], array_keys(DeliveryStatusGroups::sanitize($filters)), $label);
        }
    }

    public function testStatusBecomesTheSameEntityFilterAsTheOldParameter(): void
    {
        foreach (array_keys(WaybillStatus::FILTER_GROUPS) as $group) {
            self::assertSame(
                OrdersListFilter::apply(['status_id' => 2], $group, null, static fn(): array => []),
                $this->hooks()->filterByDeliveryStatus(['status_id' => 2], ['status_id' => 2], 'np', $group),
                $group,
            );
        }
        self::assertSame(['np_status_codes' => [7, 8]], $this->hooks()->filterByDeliveryStatus([], [], 'np', 'arrived'));
        self::assertSame(['np_without_waybill' => 1], $this->hooks()->filterByDeliveryStatus([], [], 'np', 'none'));
    }

    public function testOtherCarrierIsLeftAlone(): void
    {
        self::assertSame(['up_status_groups' => ['arrived']], $this->hooks()->filterByDeliveryStatus(['up_status_groups' => ['arrived']], [], 'up', 'arrived'));
        self::assertSame([], $this->hooks()->filterByDeliveryStatus([], [], 'np', 'nonsense'));
    }

    public function testWaybillNumberIsFound(): void
    {
        self::assertSame([3, 990061], $this->hooks()->ordersByShipmentNumber([3], '20 4512 3456 7890'));
        self::assertSame([990061], $this->hooks()->ordersByShipmentNumber([], '2045-1234-5678-90'));
    }

    public function testAnythingElseFindsNothing(): void
    {
        foreach (['Петренко', '990061', '20459999999999', '0503900000017', 'RR123456785UA', ''] as $keyword) {
            self::assertSame([7], $this->hooks()->ordersByShipmentNumber([7], $keyword), $keyword);
        }
    }

    public function testNoHubKeepsLegacy(): void
    {
        $filter = $this->backend(false, ['np_waybill' => 'arrived', 'keyword' => '20451234567890'])->listFilter(['keyword' => '20451234567890', 'page' => 1]);

        self::assertSame(['page' => 1, 'id' => [990061], 'np_status_codes' => [7, 8]], $filter);
        self::assertSame(array_keys(WaybillStatus::FILTER_GROUPS), $this->assigned['np_status_groups']);
        self::assertSame('arrived', $this->assigned['np_status_group']);
        self::assertSame('20451234567890', $this->assigned['keyword']);
    }

    public function testNoHubKeepsLegacyCounts(): void
    {
        $count = $this->backend(false, [])->countFilter(['keyword' => 'x'], ['id' => [5], 'np_status_codes' => [7, 8]]);

        self::assertSame(['id' => [5], 'np_status_codes' => [7, 8]], $count);
    }

    /** З хабом свій select мовчить (np_status_groups не присвоєно), а групу й пошук веде хаб. */
    public function testHubTakesOver(): void
    {
        $filter = $this->backend(true, ['np_waybill' => 'arrived', 'keyword' => '20451234567890'])->listFilter(['keyword' => '20451234567890', 'page' => 1]);

        self::assertSame(['keyword' => '20451234567890', 'page' => 1], $filter);
        self::assertArrayNotHasKey('np_status_groups', $this->assigned);
        self::assertArrayNotHasKey('np_status_group', $this->assigned);
        self::assertArrayNotHasKey('keyword', $this->assigned);
        self::assertArrayHasKey('np_mass_report', $this->assigned, 'масова дія лишається на тому ж хуку');
    }

    public function testHubCountsWithoutHelp(): void
    {
        self::assertSame(['keyword' => 'x'], $this->backend(true, [])->countFilter(['keyword' => 'x'], ['id' => [5], 'np_status_codes' => [7, 8]]));
    }

    /** Реєстрація ланки на неіснуючий клас кидає виняток в init() і кладе весь сайт, тож без хаба ланок немає. */
    public function testInitDoesNotRegisterHubHooksWithoutClass(): void
    {
        self::assertSame([], DeliveryFilterHub::hooks(self::HUB . 'Missing'));
    }

    public function testInitRegistersThreeHooksWhenTheHubExists(): void
    {
        $hooks = DeliveryFilterHub::hooks(HubWithAllMethods::class);

        self::assertSame([
            [[HubWithAllMethods::class, 'findDeliveryStatusFilters'], [DeliveryFilterExtender::class, 'deliveryStatusFilters']],
            [[HubWithAllMethods::class, 'filterByDeliveryStatus'], [DeliveryFilterExtender::class, 'filterByDeliveryStatus']],
            [[HubWithAllMethods::class, 'findOrderIdsByShipmentNumber'], [DeliveryFilterExtender::class, 'ordersByShipmentNumber']],
        ], $hooks);
        $init = (string)file_get_contents(dirname(__DIR__, 5) . '/Okay/Modules/Sviat/NovaPoshta/Init/Init.php');
        self::assertStringContainsString('DeliveryFilterHub::hooks(DeliveryFilterHub::HELPER)', $init);
        self::assertSame(self::HUB, DeliveryFilterHub::HELPER);
    }

    /** Адмінка запускає init() і вимкнених модулів: ланки вимкненого перевізника зареєстровані, але мусять мовчати. */
    public function testDisabledCarrierAddsNothing(): void
    {
        $hooks = $this->hooks(false);

        self::assertSame(['xx' => []], $hooks->deliveryStatusFilters(['xx' => []]));
        self::assertSame(['page' => 1], $hooks->filterByDeliveryStatus(['page' => 1], ['page' => 1], 'np', 'arrived'));
        self::assertSame([5], $hooks->ordersByShipmentNumber([5], '20 4512 3456 7890'));
    }

    /** Новіший перевізник поруч зі старішим хабом, де ще немає якоїсь ланки: реєстрація кинула б виняток в init(). */
    public function testNoHooksWhenTheHubLacksAMethod(): void
    {
        self::assertSame([], DeliveryFilterHub::hooks(HubWithoutSearch::class));
    }
}

class HubWithAllMethods
{
    public function findDeliveryStatusFilters(): array
    {
        return [];
    }

    public function filterByDeliveryStatus(array $filter, string $carrier, string $status): array
    {
        return $filter;
    }

    public function findOrderIdsByShipmentNumber(string $keyword): array
    {
        return [];
    }
}

class HubWithoutSearch
{
    public function findDeliveryStatusFilters(): array
    {
        return [];
    }

    public function filterByDeliveryStatus(array $filter, string $carrier, string $status): array
    {
        return $filter;
    }
}
