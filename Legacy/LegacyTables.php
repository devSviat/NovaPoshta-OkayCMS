<?php

namespace Okay\Modules\Sviat\NovaPoshta\Legacy;

use Okay\Core\Config;
use Okay\Core\Database;
use Okay\Core\EntityFactory;
use Okay\Core\QueryFactory;
use Okay\Core\QueryFactory\Select;
use Okay\Core\Settings;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\LanguagesEntity;
use Okay\Entities\ModulesEntity;
use Okay\Modules\Sviat\NovaPoshta\Cart\DeliveryMode;

/** LegacyData from the database; the old modules' entities may be gone, so it reads tables through the query builder. */
final class LegacyTables implements LegacyData
{
    private const string DELIVERY = '__okaycms__np_cost_delivery_data';
    private const string TRACKING_TABLE = '__sviat__novaposhta_tracking';
    private const string WAREHOUSES = '__okaycms__np_warehouses';
    private const string CITIES = '__okaycms__np_cities';
    private const string POPULAR_TABLE = '__sviat__np_popular_cities';
    private const string POPULAR_NAMES = '__sviat__np_settlements';
    private const array DELIVERY_COLUMNS = [
        'id', 'order_id', 'city_id', 'warehouse_id', 'redelivery', 'city_name', 'area_name', 'region_name', 'street', 'house', 'apartment',
        'control_payment', 'recipient_address_note', 'door_delivery', 'lifting_floor',
    ];
    private const string COD_FIELD = 'novaposhta_cost__cash_on_delivery';

    /** @var list<string>|null */
    private ?array $tables = null;
    /** @var array<string, list<string>> */
    private array $columns = [];
    private ?int $langId = null;

    public function __construct(
        private readonly Database $db,
        private readonly QueryFactory $queryFactory,
        private readonly Config $config,
        private readonly Settings $settings,
        private readonly EntityFactory $entityFactory,
    ) {
    }

    /** @param list<string> $existing table names as the database lists them */
    public static function present(array $existing, string $prefix, string $table): bool
    {
        return in_array($prefix . substr($table, 2), $existing, true);
    }

    /**
     * @param list<string> $existing
     * @param list<string> $wanted
     * @return list<string> the wanted columns that exist, in the wanted order
     */
    public static function selectable(array $existing, array $wanted): array
    {
        return array_values(array_intersect($wanted, $existing));
    }

    /** NovaposhtaCost's per-method `service_type`: where the parcel is handed to the customer. */
    public static function modeOf(?string $serviceType): ?DeliveryMode
    {
        return match ($serviceType) {
            'WarehouseWarehouse', 'DoorsWarehouse' => DeliveryMode::Points,
            'WarehouseDoors', 'DoorsDoors' => DeliveryMode::Address,
            default => null,
        };
    }

    public function deliveryRows(int $afterId, int $limit): array
    {
        if (!$this->has(self::DELIVERY)) {
            return [];
        }
        $present = self::selectable($this->columnsOf(self::DELIVERY), self::DELIVERY_COLUMNS);
        $rows = $this->select($this->queryFactory->newSelect()
            ->cols(array_map(static fn(string $c): string => 'd.' . $c, $present))
            ->from(self::DELIVERY . ' AS d')
            ->where('d.id > :after')
            ->bindValue('after', $afterId)
            ->orderBy(['d.id'])
            ->limit($limit));
        $missing = array_fill_keys(array_diff(self::DELIVERY_COLUMNS, $present), null);

        return array_map(static fn(object $row): object => (object)((array)$row + $missing), $rows);
    }

    public function trackingRows(int $afterId, int $limit): array
    {
        if (!$this->has(self::TRACKING_TABLE)) {
            return [];
        }

        return $this->select($this->queryFactory->newSelect()
            ->cols(['t.id', 't.order_id', 't.int_doc_number', 't.ref_id', 't.status_code', 't.tracking_update_at', 't.tracking_response', 't.created_at'])
            ->from(self::TRACKING_TABLE . ' AS t')
            ->where('t.id > :after')
            ->bindValue('after', $afterId)
            ->orderBy(['t.id'])
            ->limit($limit));
    }

    public function warehouse(string $ref): ?object
    {
        if ($ref === '' || !$this->has(self::WAREHOUSES)) {
            return null;
        }
        $number = in_array('number', $this->columnsOf(self::WAREHOUSES), true) ? 'w.number' : 'NULL AS number';
        $rows = $this->select($this->queryFactory->newSelect()
            ->cols(['w.ref', 'w.city_ref', $number, 'l.name'])
            ->from(self::WAREHOUSES . ' AS w')
            ->join('LEFT', '__lang_okaycms__np_warehouses AS l', 'l.warehouse_id = w.id AND l.lang_id = :lang')
            ->where('w.ref = :ref')
            ->bindValues(['ref' => $ref, 'lang' => $this->langId()])
            ->limit(1));
        if ($rows === []) {
            return null;
        }
        $row = $rows[0];
        $row->number = $row->number !== null && (int)$row->number > 0 ? (int)$row->number : null;

        return $row;
    }

    public function cityName(string $cityRef): ?string
    {
        if ($cityRef === '' || !$this->has(self::CITIES)) {
            return null;
        }
        $rows = $this->select($this->queryFactory->newSelect()
            ->cols(['l.name'])
            ->from(self::CITIES . ' AS c')
            ->join('LEFT', '__lang_okaycms__np_cities AS l', 'l.city_id = c.id AND l.lang_id = :lang')
            ->where('c.ref = :ref')
            ->bindValues(['ref' => $cityRef, 'lang' => $this->langId()])
            ->limit(1));
        $name = $rows !== [] ? trim((string)$rows[0]->name) : '';

        return $name !== '' ? $name : null;
    }

    public function codPaymentIds(): array
    {
        if (!in_array(self::COD_FIELD, $this->columnsOf('__payment_methods'), true)) {
            return [];
        }
        $rows = $this->select($this->queryFactory->newSelect()
            ->cols(['p.id'])
            ->from('__payment_methods AS p')
            ->where('p.' . self::COD_FIELD . ' = 1')
            ->orderBy(['p.id']));

        return array_map(static fn(object $r): int => (int)$r->id, $rows);
    }

    public function volumes(int $afterId, int $limit): array
    {
        if (!in_array('volume', $this->columnsOf('__variants'), true)) {
            return [];
        }

        return $this->select($this->queryFactory->newSelect()
            ->cols(['v.id', 'v.volume'])
            ->from('__variants AS v')
            ->where('v.id > :after')
            ->where('v.volume > 0')
            ->bindValue('after', $afterId)
            ->orderBy(['v.id'])
            ->limit($limit));
    }

    public function popularCities(): array
    {
        if (!$this->has(self::POPULAR_TABLE)) {
            return [];
        }
        $names = $this->has(self::POPULAR_NAMES)
            ? '(SELECT s.city_name FROM ' . self::POPULAR_NAMES . ' AS s WHERE s.city_ref = p.city_ref LIMIT 1) AS name'
            : 'NULL AS name';

        return $this->select($this->queryFactory->newSelect()
            ->cols(['p.city_ref', $names, 'p.position'])
            ->from(self::POPULAR_TABLE . ' AS p')
            ->orderBy(['p.position', 'p.id']));
    }

    public function setting(string $name): ?string
    {
        $value = $this->settings->get($name);

        return is_scalar($value) && trim((string)$value) !== '' ? trim((string)$value) : null;
    }

    public function counts(): array
    {
        $count = function (string $table, ?string $where = null): int {
            if (!$this->has($table)) {
                return 0;
            }
            $select = $this->queryFactory->newSelect()->cols(['COUNT(*) AS n'])->from($table);
            if ($where !== null) {
                $select->where($where);
            }
            $rows = $this->select($select);

            return $rows !== [] ? (int)$rows[0]->n : 0;
        };

        return [
            'orders' => $count(self::DELIVERY, 'order_id > 0'),
            'waybills' => $count(self::TRACKING_TABLE),
            'payments' => count($this->codPaymentIds()),
            'volumes' => in_array('volume', $this->columnsOf('__variants'), true) ? $count('__variants', 'volume > 0') : 0,
            'popular' => $count(self::POPULAR_TABLE),
        ];
    }

    public function enabledModules(): array
    {
        $found = [];
        foreach ([self::COST, self::TRACKING, self::POPULAR] as $name) {
            [$vendor, $module] = explode('/', $name);
            if ($this->entityFactory->get(ModulesEntity::class)->findOne(['vendor' => $vendor, 'module_name' => $module, 'enabled' => 1])) {
                $found[] = $name;
            }
        }

        return $found;
    }

    public function legacyDeliveries(): array
    {
        $module = $this->entityFactory->get(ModulesEntity::class)->findOne(['vendor' => 'OkayCMS', 'module_name' => 'NovaposhtaCost']);
        if (!$module) {
            return [];
        }
        $deliveries = $this->entityFactory->get(DeliveriesEntity::class);
        $found = [];
        foreach ($deliveries->noLimit()->find(['module_id' => (int)$module->id]) as $delivery) {
            $settings = $deliveries->getSettings((int)$delivery->id);
            $found[] = (object)[
                'id' => (int)$delivery->id,
                'name' => (string)$delivery->name,
                'service_type' => is_array($settings) && is_string($settings['service_type'] ?? null) ? $settings['service_type'] : null,
            ];
        }

        return $found;
    }

    private function has(string $table): bool
    {
        if ($this->tables === null) {
            $this->tables = [];
            if ($this->db->query($this->queryFactory->newSqlQuery()->setStatement('SHOW TABLES')) !== false) {
                foreach ($this->db->results() as $row) {
                    $this->tables[] = (string)current((array)$row);
                }
            }
        }

        return self::present($this->tables, (string)$this->config->get('db_prefix'), $table);
    }

    /** @return list<string> */
    private function columnsOf(string $table): array
    {
        if (!isset($this->columns[$table])) {
            $this->columns[$table] = [];
            if ($this->has($table) && $this->db->query($this->queryFactory->newSqlQuery()->setStatement('SHOW COLUMNS FROM ' . $table)) !== false) {
                foreach ($this->db->results() as $row) {
                    $this->columns[$table][] = (string)$row->Field;
                }
            }
        }

        return $this->columns[$table];
    }

    /** @return list<object> */
    private function select(Select $select): array
    {
        return $this->db->query($select) === false ? [] : array_values($this->db->results());
    }

    private function langId(): int
    {
        if ($this->langId === null) {
            $languages = $this->entityFactory->get(LanguagesEntity::class);
            $lang = $languages->findOne(['label' => 'ua']) ?: $languages->findOne([]);
            $this->langId = $lang ? (int)$lang->id : 0;
        }

        return $this->langId;
    }
}
